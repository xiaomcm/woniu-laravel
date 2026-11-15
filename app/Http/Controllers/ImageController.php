<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response as HttpResponse;
use App\Core\Session;
use App\Core\Auth;
use App\Core\Database as DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Setting;
use App\Models\Album;
use App\Models\Image;
use App\Models\Storage as StorageModel;
use App\Services\Blank;
use App\Services\Cover;
use App\Services\DriveService;
use App\Services\ImageProcessor;
use App\Services\Links;
use App\Services\Mosaic;
use App\Services\ShareService;
use App\Storage\LocalStorage;
use App\Storage\Manager;

class ImageController extends Controller
{
    public function index(): HttpResponse
    {
        $user = Auth::requireLogin();
        $page = max(1, Request::int('page', 1));
        $perPage = max(6, min(120, Request::int('per_page', Setting::int('page_size', 24))));

        $filters = [
            'user_id'  => (int) $user['id'],
            'album_id' => Request::input('album_id', '') === '' ? null : Request::int('album_id'),
            'keyword'  => (string) Request::input('keyword', ''),
            'status'   => Request::input('status', ''),
            'sort'     => (string) Request::input('sort', 'newest'),
            'trashed'  => Request::bool('trashed', false),
        ];

        $result = Image::paginate($filters, $page, $perPage);

        return $this->view('images', [
            'pageTitle' => '图片管理',
            'images'    => $result['items'],
            'pager'     => $result,
            'albums'    => Album::allForUser((int) $user['id']),
            'filters'   => $filters,
        ]);
    }

    public function update(string $id): void
    {
        $this->validateCsrf();
        $user = Auth::requireLogin();
        $image = Image::findForUser((int) $id, (int) $user['id']);
        if ($image === null) {
            $this->api(false, '图片不存在', null, 404);
        }

        $data = [];
        $name = (string) Request::input('name', '');
        if ($name !== '') {
            $data['name'] = mb_substr($name, 0, 100);
        }
        if (Request::input('description') !== null) {
            $data['description'] = mb_substr((string) Request::input('description', ''), 0, 500);
        }
        if (Request::input('tags') !== null) {
            $raw = (string) Request::input('tags', '');
            $parts = preg_split('/[,，\s]+/u', $raw) ?: [];
            $parts = array_values(array_unique(array_filter(array_map('trim', $parts))));
            $data['tags'] = implode(',', array_slice($parts, 0, 8));
        }
        if (Request::input('is_public') !== null || Request::input('is_public_present') !== null) {
            $data['is_public'] = Request::bool('is_public', false) ? 1 : 0;
        }
        if (Request::input('album_id') !== null) {
            $albumId = Request::int('album_id');
            if ($albumId > 0) {
                $album = Album::find($albumId);
                if ($album === null || (int) $album['user_id'] !== (int) $user['id']) {
                    $this->api(false, '相册不存在');
                }
            }
            $data['album_id'] = $albumId > 0 ? $albumId : null;
        }
        // 访问密码：留空表示不动，填了才改；清空要走 clear_password，否则误提交空值会把密码抹掉
        $password = trim((string) Request::input('password', ''));
        $clearPwd = Request::bool('clear_password', false);
        if ($password !== '') {
            if (mb_strlen($password) < 4 || mb_strlen($password) > 32) {
                $this->api(false, '密码长度需在 4-32 位之间');
            }
            $data['password'] = ShareService::hash($password);
        } elseif ($clearPwd) {
            $data['password'] = null;
        }

        if ($data !== []) {
            DB::update('images', $data + ['updated_at' => now()], 'id = :id', ['id' => (int) $id]);
            Album::recount((int) $image['album_id']);
            if (!empty($data['album_id'])) {
                Album::recount((int) $data['album_id']);
            }
            // 密码被改写或清除时，作废所有已解锁的会话，避免旧密码解锁的人继续看
            if (array_key_exists('password', $data)) {
                Session::forget('_share.image:' . (int) $id);
            }
        }
        $this->api(true, '已保存', Links::apiPayload(Image::find((int) $id) ?? $image));
    }

    public function delete(): void
    {
        $this->validateCsrf();
        $user = Auth::requireLogin();
        $ids = $this->ids();
        if ($ids === []) {
            $this->api(false, '请选择要删除的图片');
        }
        $count = 0;
        foreach ($ids as $id) {
            $image = Image::findForUser($id, (int) $user['id']);
            if ($image === null) {
                continue;
            }
            Image::softDelete($id);
            Album::recount((int) $image['album_id']);
            $count++;
        }
        \App\Models\Log::record((int) $user['id'], 'image.delete', implode(',', $ids));
        $this->api(true, '已移入回收站（' . $count . ' 张）', ['count' => $count]);
    }

    public function restore(): void
    {
        $this->validateCsrf();
        $user = Auth::requireLogin();
        foreach ($this->ids() as $id) {
            $image = Image::findForUser($id, (int) $user['id']);
            if ($image !== null) {
                Image::restore($id);
                Album::recount((int) $image['album_id']);
            }
        }
        $this->api(true, '已恢复');
    }

    public function destroy(): void
    {
        $this->validateCsrf();
        $user = Auth::requireLogin();
        $count = 0;
        foreach ($this->ids() as $id) {
            $image = Image::findForUser($id, (int) $user['id']);
            if ($image === null) {
                continue;
            }
            $this->purge($image);
            $count++;
        }
        $this->api(true, '已彻底删除（' . $count . ' 张）', ['count' => $count]);
    }

    public function move(): void
    {
        $this->validateCsrf();
        $user = Auth::requireLogin();
        $ids = $this->ids();
        $albumId = Request::int('album_id');
        if ($albumId > 0) {
            $album = Album::find($albumId);
            if ($album === null || (int) $album['user_id'] !== (int) $user['id']) {
                $this->api(false, '相册不存在');
            }
        }
        foreach ($ids as $id) {
            if (Image::findForUser($id, (int) $user['id']) === null) {
                continue;
            }
            DB::update('images', ['album_id' => $albumId > 0 ? $albumId : null, 'updated_at' => now()], 'id = :id', ['id' => $id]);
        }
        if ($albumId > 0) {
            Album::recount($albumId);
        }
        $this->api(true, '已移动');
    }



    public function purge(array $image): void
    {
        $storage = StorageModel::find((int) $image['storage_id']);
        if ($storage !== null) {
            Manager::make($storage)->delete((string) $image['pathname']);
        }
        $thumb = BASE_PATH . '/storage/thumbs/' . substr((string) $image['sha1'], 0, 2) . '/' . $image['sha1'] . '.jpg';
        if (is_file($thumb)) {
            @unlink($thumb);
        }
        Image::forceDeleteById((int) $image['id']);
    }



    public function detail(string $key): HttpResponse
    {
        $image = Image::findByKey($key);
        if ($image === null) {
            abort(404, '图片不存在或已被删除');
        }

        $viewer = Auth::user();
        $isOwner = $viewer !== null && (int) $viewer['id'] === (int) $image['user_id'];
        $status = (int) $image['status'];

        // 已判定违规下线：前台一律打不开，管理员也走后台审核入口
        if ($status === 2) {
            abort(410, '该图片因违规已被下线');
        }
        if ($status !== 1 && !$isOwner && !is_admin()) {
            abort(403, '该图片正在审核中');
        }
        if ((int) $image['is_public'] !== 1 && !$isOwner && !is_admin()) {
            abort(403, '该图片未公开');
        }

        // 分享密码：访客未解锁时只给密码框，不渲染图片地址与引用代码
        if (!ShareService::granted('image', (int) $image['id'], $image, $isOwner || is_admin())) {
            return $this->view('share_lock', [
                'pageTitle' => '需要访问密码',
                'kind'      => '图片',
                'action'    => url('i/' . $key),
                'backUrl'   => url('explore'),
                'name'      => (string) $image['name'],
            ], 'layouts/site');
        }

        if (!$isOwner) {
            Image::incrementViews((int) $image['id']);
        }

        $owner = DB::fetch(
            'SELECT id, name, avatar, group_id FROM ' . DB::table('users') . ' WHERE id = :id',
            ['id' => (int) $image['user_id']]
        );

        // 云处理派生地址：只有配置了云处理驱动器且开关打开时才生成
        $cloudVariants = [];
        if (Setting::bool('cloud_process_enabled', true) && $owner !== null) {
            foreach ([400, 800, 1200] as $width) {
                $derived = DriveService::cloudUrl(
                    (int) $owner['group_id'],
                    (int) $image['storage_id'],
                    (string) $image['url'],
                    ['width' => $width]
                );
                if ($derived !== (string) $image['url'] && $derived !== '') {
                    $cloudVariants[$width] = $derived;
                }
            }
        }

        return $this->view('image_detail', [
            'pageTitle'      => (string) $image['name'],
            'image'          => $image,
            'links'          => Links::of($image),
            'owner'          => $owner,
            'isOwner'        => $isOwner,
            'related'        => Image::related($image, 6),
            'hasPassword'    => trim((string) ($image['password'] ?? '')) !== '',
            'cloudVariants'  => $cloudVariants,
            'tags'           => array_values(array_filter(array_map('trim', preg_split('/[,，\s]+/u', (string) ($image['tags'] ?? ''))))),
        ], 'layouts/site');
    }

    /**
     * 访客提交分享密码
     */
    public function unlockShare(string $key): void
    {
        $this->validateCsrf();

        $image = Image::findByKey($key);
        if ($image === null) {
            abort(404, '图片不存在或已被删除');
        }

        $guard = '_share_try:image:' . (int) $image['id'];
        $tries = (int) Session::get($guard . '.n', 0);
        $until = (int) Session::get($guard . '.t', 0);
        if ($tries >= 5 && time() < $until) {
            flash('error', '尝试次数过多，请 ' . max(1, (int) ceil(($until - time()) / 60)) . ' 分钟后再试');
            redirect('i/' . $key);
        }

        $password = (string) Request::input('password', '');
        if (!ShareService::unlock('image', (int) $image['id'], $image, $password)) {
            Session::put($guard, ['n' => $tries + 1, 't' => time() + 300]);
            flash('error', '密码不正确');
            redirect('i/' . $key);
        }

        Session::forget($guard);
        redirect('i/' . $key);
    }

    /**
     * 所有者设置图片的公开状态与分享密码
     */
    public function updateShare(string $id): void
    {
        $this->validateCsrf();
        $user = Auth::requireLogin();

        $image = Image::findForUser((int) $id, (int) $user['id']);
        if ($image === null) {
            abort(404, '图片不存在');
        }

        $data = [
            'is_public'  => Request::bool('is_public', false) ? 1 : 0,
            'updated_at' => now(),
        ];

        $password = trim((string) Request::input('password', ''));
        if ($password !== '') {
            if (mb_strlen($password) < 4 || mb_strlen($password) > 32) {
                flash('error', '密码长度需在 4-32 位之间');
                redirect('i/' . (string) $image['sha1']);
            }
            $data['password'] = ShareService::hash($password);
        } elseif (Request::bool('clear_password', false)) {
            $data['password'] = null;
        }

        DB::update('images', $data, 'id = :id', ['id' => (int) $id]);
        if (array_key_exists('password', $data)) {
            Session::forget('_share.image:' . (int) $id);
        }
        flash('success', '分享设置已保存');
        redirect('i/' . (string) $image['sha1']);
    }

    public function download(): void
    {
        $this->validateCsrf();
        $user = Auth::requireLogin();

        if (!Setting::bool('download_enabled', true)) {
            $this->api(false, '站点未开启打包下载');
        }
        $ids = $this->ids();
        if ($ids === []) {
            $this->api(false, '请选择要下载的图片');
        }
        if (!class_exists(\ZipArchive::class)) {
            $this->api(false, '服务器未安装 zip 扩展');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'wnzip');
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            $this->api(false, '创建压缩包失败');
        }

        $count = 0;
        $used = [];
        foreach ($ids as $id) {
            $image = is_admin() ? Image::find($id) : Image::findForUser($id, (int) $user['id']);
            if ($image === null) {
                continue;
            }
            $binary = $this->readImageBinary($image);
            if ($binary === null) {
                continue;
            }
            $name = trim((string) ($image['original_name'] ?? '')) ?: ($image['sha1'] . '.' . $image['extension']);
            $name = preg_replace('/[\\\\\\/\\:\\*\\?"<>\\|]+/u', '_', $name) ?: ('image-' . $id);
            if (isset($used[$name])) {
                $used[$name]++;
                $dot = strrpos($name, '.');
                $name = $dot === false
                    ? $name . '-' . $used[$name]
                    : substr($name, 0, $dot) . '-' . $used[$name] . substr($name, $dot);
            } else {
                $used[$name] = 1;
            }
            $zip->addFromString($name, $binary);
            $count++;
        }
        $zip->close();

        if ($count === 0) {
            @unlink($tmp);
            $this->api(false, '没有可下载的图片');
        }

        $filename = 'WONIU-' . date('Ymd-His') . '.zip';
        if (!headers_sent()) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . (string) filesize($tmp));
            header('Cache-Control: no-store');
        }
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    private function readImageBinary(array $image): ?string
    {
        $storage = StorageModel::find((int) $image['storage_id']);
        if ($storage !== null && $storage['type'] === 'local') {
            $driver = Manager::make($storage);
            if ($driver instanceof LocalStorage) {
                $file = $driver->root() . '/' . ltrim((string) $image['pathname'], '/');
                if (is_file($file)) {
                    $data = @file_get_contents($file);
                    return $data === false ? null : $data;
                }
            }
        }
        $context = stream_context_create(['http' => ['timeout' => 20]]);
        $data = @file_get_contents((string) $image['url'], false, $context);
        return $data === false ? null : $data;
    }

    public function serve(string $path): void
    {
        $path = ltrim($path, '/');
        if (strpos($path, '..') !== false) {
            abort(404);
        }





        $rows = DB::fetchAll(
            'SELECT * FROM ' . DB::table('images') . ' WHERE pathname = :p ORDER BY id ASC',
            ['p' => $path]
        );
        $image = $rows[0] ?? null;

        if ($rows !== []) {
            $approved = false;
            $rejected = false;
            $owned = false;
            $userId = Auth::id();
            foreach ($rows as $row) {
                $rowStatus = (int) $row['status'];
                if ($rowStatus === 1) {
                    $approved = true;
                }
                if ($rowStatus === 2) {
                    $rejected = true;
                }
                if ($userId !== null && (int) $row['user_id'] === $userId) {
                    $owned = true;
                }
            }
            // 违规下线的图片：原图直链对所有人失效，管理员只能走后台预览入口
            if ($rejected && !$approved) {
                if (!headers_sent()) {
                    header('Cache-Control: no-store, must-revalidate');
                }
                abort(404, '该图片已被下线');
            }
            if (!$approved && !$owned && !is_admin()) {
                if (!headers_sent()) {
                    header('Cache-Control: no-store, must-revalidate');
                }
                abort(403, '该图片正在审核中');
            }

            // 分享密码要管住原图直链，不然锁了详情页、直链照样裸奔
            $userId = Auth::id();
            foreach ($rows as $row) {
                if (trim((string) ($row['password'] ?? '')) === '') {
                    continue;
                }
                $owner = $userId !== null && (int) $row['user_id'] === $userId;
                if (!$owner && !is_admin() && !ShareService::unlocked('image', (int) $row['id'], $row)) {
                    if (!headers_sent()) {
                        header('Cache-Control: no-store, must-revalidate');
                    }
                    abort(404, '该图片需要访问密码');
                }
            }
        }

        if (Setting::bool('hotlink_enabled', false) && !$this->hotlinkAllowed()) {
            abort(403, '该站点已开启防盗链');
        }

        $storage = $image === null ? null : StorageModel::find((int) $image['storage_id']);
        if ($storage !== null && $storage['type'] === 'local') {
            $driver = Manager::make($storage);
            if ($driver instanceof LocalStorage) {
                $this->outputLocal($driver->root() . '/' . $path, $path);
            }
        }
        if ($image !== null) {
            redirect((string) $image['url']);
        }

        $localRoot = BASE_PATH . '/storage/uploads/' . $path;
        if (is_file($localRoot)) {
            $this->outputLocal($localRoot, $path);
        }
        abort(404);
    }

    /**
     * 本地文件输出：带 ?w&h&fit&q&f&filter 时按参数实时处理并缓存
     */
    private function outputLocal(string $file, string $path): void
    {
        if (!is_file($file)) {
            abort(404);
        }

        $ops = [
            'width'   => Request::int('w', 0),
            'height'  => Request::int('h', 0),
            'fit'     => (string) Request::input('fit', 'cover'),
            'quality' => Request::int('q', 0),
            'format'  => (string) Request::input('f', ''),
            'filter'  => (string) Request::input('filter', ''),
            'rotate'  => Request::int('rotate', 0),
            'flip'    => (string) Request::input('flip', ''),
        ];
        $ops = array_filter($ops, static fn ($v) => $v !== '' && $v !== 0);

        if ($ops === []) {
            Response::file($file);
            return;
        }

        $mime = ImageProcessor::mimeFromExtension(strtolower(pathinfo($file, PATHINFO_EXTENSION)));
        $cacheDir = BASE_PATH . '/storage/cache/proc';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $ext = strtolower((string) ($ops['format'] ?? '')) ?: pathinfo($file, PATHINFO_EXTENSION);
        $key = sha1($path . '|' . json_encode($ops) . '|' . filemtime($file)) . '.' . $ext;
        $cached = $cacheDir . '/' . $key;

        if (!is_file($cached)) {
            $result = ImageProcessor::transform((string) file_get_contents($file), $mime, $ops);
            if ($result === null) {
                // 不支持的格式（SVG 等）按原图输出
                Response::file($file);
                return;
            }
            @file_put_contents($cached, $result['binary']);
        }

        $outMime = ImageProcessor::mimeFromExtension(strtolower(pathinfo($cached, PATHINFO_EXTENSION)));
        header('Content-Type: ' . $outMime);
        header('Content-Length: ' . (string) filesize($cached));
        header('Cache-Control: public, max-age=2592000');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int) filemtime($cached)) . ' GMT');
        readfile($cached);
        exit;
    }

    /**
     * 空白占位图：审核中/已拒绝/已删除的图在列表里只留一块白，尺寸跟原图一致
     * 公开可访问，没有敏感内容，长缓存减少重复请求
     */
    public function blank(string $spec): void
    {
        if (!preg_match('/^(\d{1,5})x(\d{1,5})(?:\.jpg)?$/', ltrim($spec, '/'), $m)) {
            abort(404);
        }
        $file = Blank::path((int) $m[1], (int) $m[2]);
        if ($file === null) {
            abort(404);
        }
        Response::file($file, null, 2592000);
    }

    public function thumb(string $path): void
    {
        $path = ltrim($path, '/');
        if (strpos($path, '..') !== false) {
            abort(404);
        }
        // 缩略图文件名就是 {sha1}.jpg，先取出来，后面生成与闸门判定都用它
        $sha1 = basename($path, '.jpg');
        if ($sha1 === '') {
            abort(404);
        }

        $file = BASE_PATH . '/storage/thumbs/' . $path;
        // 宽度小于 thumb_width 的图上传时按策略不生成缩略图（thumb_url 为空），
        // 老数据也可能因为早期问题没落盘。这里按需补一份，否则列表只能退回占位图，
        // 占位图的比例和真图对不上，整列高度就会错位
        if (!is_file($file)) {
            $file = $this->ensureThumbFile($sha1, $file);
            if ($file === null) {
                // 补不出来（SVG 这类 GD 处理不了的格式、云端没有本地副本）：
                // 未加密的图直接回原图，加密图仍按 404 处理，不从这里漏内容
                $fallback = DB::fetch(
                    'SELECT * FROM ' . DB::table('images') . '
                     WHERE sha1 = :k AND deleted_at IS NULL AND status = 1
                     ORDER BY id DESC LIMIT 1',
                    ['k' => $sha1]
                );
                if ($fallback !== null
                    && trim((string) ($fallback['password'] ?? '')) === ''
                    && (string) $fallback['url'] !== '') {
                    redirect((string) $fallback['url']);
                }
                abort(404);
            }
        }

        // 与原图走同一套可见性判定：缩略图不能成为绕过下线与分享密码的后门
        $state = $this->visibility($sha1);

        // 没有已通过的记录（审核中/已拒绝/已删除）一律 404
        if ($state === 'hidden') {
            if (!headers_sent()) {
                header('Cache-Control: no-store, must-revalidate');
                header('Pragma: no-cache');
            }
            abort(404);
        }

        // 加密未解锁：给马赛克预览，卡片还能看个大概色调，但细节不出站
        if ($state === 'locked') {
            $mosaic = Mosaic::ensure($file, $sha1);
            if ($mosaic === null) {
                abort(404);
            }
            Response::file($mosaic, null, 0, true);
        }

        Response::file($file, null, 300, true);
    }

    /**
     * 广场封面：交给所有卡片一个 1280×860 的定点尺寸
     * 闸门与 /thumbs 完全一致，加密图同样只出马赛克
     */
    public function cover(string $path): void
    {
        $path = ltrim($path, '/');
        if (strpos($path, '..') !== false) {
            abort(404);
        }
        $sha1 = basename($path, '.jpg');
        if ($sha1 === '' || !preg_match('/^[a-f0-9]{40}$/i', $sha1)) {
            abort(404);
        }

        $state = $this->visibility($sha1);
        if ($state === 'hidden') {
            abort(404);
        }

        // 加密未解锁：马赛克也出 1280×860，卡片物理尺寸和普通图一样
        // 这一支不生成清晰封面，省一次大图 GD，也避免清晰版本白落盘
        if ($state === 'locked') {
            $thumb = BASE_PATH . '/storage/thumbs/' . substr($sha1, 0, 2) . '/' . $sha1 . '.jpg';
            if (!is_file($thumb)) {
                $thumb = $this->ensureThumbFile($sha1, $thumb);
            }
            if ($thumb === null) {
                abort(404);
            }
            $mosaic = Mosaic::ensure($thumb, $sha1, Cover::W, Cover::H);
            if ($mosaic === null) {
                abort(404);
            }
            Response::file($mosaic, null, 0, true);
        }

        // 源图找不到（云端托管、SVG）就先拿缩略图顶上，比例问题由前端的 object-fit 兜住
        $source = $this->sourcePath($sha1);
        $file   = $source === null ? null : Cover::ensure($source, $sha1);
        if ($file === null) {
            $thumb = BASE_PATH . '/storage/thumbs/' . substr($sha1, 0, 2) . '/' . $sha1 . '.jpg';
            $file = is_file($thumb) ? $thumb : $this->ensureThumbFile($sha1, $thumb);
        }
        if ($file === null) {
            abort(404);
        }

        Response::file($file, null, 2592000, false);
    }

    /**
     * 按 sha1 判定公开可见性，thumb / cover 共用一套规则
     * hidden = 没有已通过记录；locked = 加密且当前访客没解锁；ok = 可以出图
     */
    private function visibility(string $sha1): string
    {
        // 同一文件可能被多次上传（同 sha1 多条记录），与 serve 一样按行判定，不能只看第一条
        $rows = DB::fetchAll(
            'SELECT i.*, a.password AS album_password FROM ' . DB::table('images') . ' i
             LEFT JOIN ' . DB::table('albums') . ' a ON a.id = i.album_id
             WHERE i.sha1 = :k AND i.deleted_at IS NULL',
            ['k' => $sha1]
        );

        $viewer  = Auth::user();
        $userId  = $viewer === null ? 0 : (int) $viewer['id'];
        $isAdmin = is_admin();
        $hasApproved = false;
        foreach ($rows as $row) {
            if ((int) $row['status'] !== 1) {
                continue;
            }
            $hasApproved = true;
            $imageLocked = trim((string) ($row['password'] ?? '')) !== '';
            $albumLocked = trim((string) ($row['album_password'] ?? '')) !== '';
            if (!$imageLocked && !$albumLocked) {
                return 'ok';
            }
            $isOwner = $userId > 0 && $userId === (int) $row['user_id'];
            $unlocked = ShareService::unlocked('image', (int) $row['id'], $row)
                || ((int) ($row['album_id'] ?? 0) > 0 && ShareService::unlocked('album', (int) $row['album_id']));
            if ($isOwner || $isAdmin || $unlocked) {
                return 'ok';
            }
        }

        return $hasApproved ? 'locked' : 'hidden';
    }

    /**
     * 找原图在本地磁盘上的落点，云端储存没有本地副本就返回 null
     */
    private function sourcePath(string $sha1): ?string
    {
        $row = DB::fetch(
            'SELECT * FROM ' . DB::table('images') . '
             WHERE sha1 = :k AND deleted_at IS NULL AND status = 1
             ORDER BY id DESC LIMIT 1',
            ['k' => $sha1]
        );
        if ($row === null) {
            return null;
        }

        $pathname = ltrim((string) $row['pathname'], '/');
        $storage = StorageModel::find((int) $row['storage_id']);
        if ($storage !== null && $storage['type'] === 'local') {
            $driver = Manager::make($storage);
            if ($driver instanceof LocalStorage) {
                $path = $driver->root() . '/' . $pathname;
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        $local = BASE_PATH . '/storage/uploads/' . $pathname;
        return is_file($local) ? $local : null;
    }

    /**
     * 缩略图缺失时按需补生成：小图（宽度 ≤ thumb_width）与历史数据都走这里
     * 只读本地磁盘上的原图，云端存储没本地副本就返回 null，由调用方 404
     */
    private function ensureThumbFile(string $sha1, string $target): ?string
    {
        $row = DB::fetch(
            'SELECT * FROM ' . DB::table('images') . '
             WHERE sha1 = :k AND deleted_at IS NULL
             ORDER BY CASE WHEN status = 1 THEN 0 ELSE 1 END, id DESC LIMIT 1',
            ['k' => $sha1]
        );
        if ($row === null) {
            return null;
        }

        $pathname = ltrim((string) $row['pathname'], '/');
        $source = null;
        $storage = StorageModel::find((int) $row['storage_id']);
        if ($storage !== null && $storage['type'] === 'local') {
            $driver = Manager::make($storage);
            if ($driver instanceof LocalStorage) {
                $source = $driver->root() . '/' . $pathname;
            }
        }
        if ($source === null || !is_file($source)) {
            $local = BASE_PATH . '/storage/uploads/' . $pathname;
            $source = is_file($local) ? $local : null;
        }
        if ($source === null) {
            return null;
        }

        $mime = ImageProcessor::mimeFromExtension(strtolower(pathinfo($source, PATHINFO_EXTENSION)));
        $thumb = ImageProcessor::thumbnail(
            (string) file_get_contents($source),
            $mime,
            max(200, Setting::int('thumb_width', 360))
        );
        if ($thumb === null) {
            return null;
        }

        $dir = dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }
        if (@file_put_contents($target, $thumb['binary']) === false) {
            return null;
        }
        return $target;
    }

    /**
     * 「我的图片」专用缩略图入口：本人看自己的加密图不受分享密码限制
     * 待审/已拒绝的图连本人也只给白块，内容要等审核通过，避免列表里出现「能看又不能看」的两套规则
     */
    public function ownThumb(string $id): void
    {
        $user = Auth::requireLogin();
        $image = Image::find((int) $id);
        if ($image === null) {
            abort(404);
        }
        if ((int) $image['user_id'] !== (int) $user['id'] && !is_admin()) {
            abort(403);
        }

        // 未过审或已进回收站：白块，比例照原图，卡片高度不会跳
        if ((int) $image['status'] !== 1 || ($image['deleted_at'] ?? null) !== null) {
            $blank = Blank::path((int) $image['width'], (int) $image['height']);
            if ($blank !== null) {
                Response::file($blank, null, 0, true);
            }
            abort(404);
        }

        $sha1 = (string) $image['sha1'];
        $thumb = BASE_PATH . '/storage/thumbs/' . substr($sha1, 0, 2) . '/' . $sha1 . '.jpg';
        // 小图和早期数据可能没落缩略图，按需补一份；补不出来再退回远端地址
        if (!is_file($thumb)) {
            $thumb = $this->ensureThumbFile($sha1, $thumb);
        }
        if ($thumb !== null && is_file($thumb)) {
            Response::file($thumb, null, 300, true);
        }
        if ((string) $image['url'] !== '') {
            redirect((string) $image['url']);
        }
        // 远端也没副本：给一块白，别让 <img> 破了之后把 alt 文字顶在卡片左上角
        $blank = Blank::path((int) $image['width'], (int) $image['height']);
        if ($blank !== null) {
            Response::file($blank, null, 300, true);
        }
        abort(404);
    }

    private function hotlinkAllowed(): bool
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer === '') {
            return true;
        }
        $host = (string) parse_url($referer, PHP_URL_HOST);
        $siteHost = (string) parse_url(base_url(), PHP_URL_HOST);
        if ($host === '' || strcasecmp($host, $siteHost) === 0) {
            return true;
        }
        $allowed = array_filter(array_map('trim', explode(',', (string) Setting::get('hotlink_domains', ''))));
        foreach ($allowed as $domain) {
            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = rtrim($domain, '/');
            if ($domain !== '' && (strcasecmp($host, $domain) === 0 || str_ends_with(strtolower($host), '.' . strtolower($domain)))) {
                return true;
            }
        }
        return false;
    }
}
