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
use App\Core\Request;
use App\Models\Album;
use App\Models\Image;
use App\Services\ShareService;

class AlbumController extends Controller
{
    public function index(): HttpResponse
    {
        $user = Auth::requireLogin();
        $albums = Album::allForUser((int) $user['id']);
        $counts = [];
        foreach ($albums as $album) {
            $counts[(int) $album['id']] = (int) $album['image_num'];
        }
        return $this->view('albums', [
            'pageTitle' => '我的相册',
            'albums'    => $albums,
            'counts'    => $counts,
        ]);
    }

    public function store(): void
    {
        $this->validateCsrf();
        $user = Auth::requireLogin();
        $name = trim((string) Request::input('name', ''));
        if ($name === '' || mb_strlen($name) > 40) {
            $this->api(false, '相册名不能为空且不超过 40 字');
        }
        $id = Album::create(
            (int) $user['id'],
            $name,
            mb_substr((string) Request::input('intro', ''), 0, 200),
            Request::bool('is_public', false)
        );

        // 创建时如果顺手填了密码，直接写进去，省一次编辑
        $password = trim((string) Request::input('password', ''));
        if ($password !== '') {
            if (mb_strlen($password) < 4 || mb_strlen($password) > 32) {
                $this->api(false, '相册已创建，但密码需 4-32 位，请重新编辑设置');
            }
            Album::updateById($id, ['password' => ShareService::hash($password)]);
        }

        $album = Album::find($id);
        $this->api(true, '创建成功', $this->payload($album ?? []));
    }

    public function update(string $id): void
    {
        $this->validateCsrf();
        $user = Auth::requireLogin();
        $album = Album::find((int) $id);
        if ($album === null || (int) $album['user_id'] !== (int) $user['id']) {
            $this->api(false, '相册不存在', null, 404);
        }
        $data = [];
        $name = trim((string) Request::input('name', ''));
        if ($name !== '') {
            $data['name'] = mb_substr($name, 0, 40);
        }
        if (Request::input('intro') !== null) {
            $data['intro'] = mb_substr((string) Request::input('intro', ''), 0, 200);
        }
        if (Request::input('is_public') !== null) {
            $isPublic = Request::bool('is_public', false);
            $data['is_public'] = $isPublic ? 1 : 0;
            if ($isPublic && empty($album['key'])) {
                $data['key'] = Album::generateKey();
            }
        }

        // 密码留空表示不改；勾选 clear_password 才清除
        $password = trim((string) Request::input('password', ''));
        if ($password !== '') {
            if (mb_strlen($password) < 4 || mb_strlen($password) > 32) {
                $this->api(false, '密码长度需在 4-32 位之间');
            }
            $data['password'] = ShareService::hash($password);
        } elseif (Request::bool('clear_password', false)) {
            $data['password'] = null;
        }

        Album::updateById((int) $id, $data);
        $this->api(true, '已保存', $this->payload(Album::find((int) $id) ?? $album));
    }

    public function delete(string $id): void
    {
        $this->validateCsrf();
        $user = Auth::requireLogin();
        $album = Album::find((int) $id);
        if ($album === null || (int) $album['user_id'] !== (int) $user['id']) {
            $this->api(false, '相册不存在', null, 404);
        }
        Album::deleteById((int) $id);
        $this->api(true, '已删除，相册内图片已移动到未分类');
    }

    public function show(string $id): HttpResponse
    {
        $user = Auth::requireLogin();
        $album = Album::find((int) $id);
        if ($album === null) {
            abort(404, '相册不存在');
        }
        if ((int) $album['user_id'] !== (int) $user['id'] && !is_admin()) {
            abort(403, '无权查看该相册');
        }
        $page = max(1, Request::int('page', 1));
        $result = Image::paginate(['album_id' => (int) $id, 'user_id' => (int) $album['user_id']], $page, 36);
        return $this->view('album_show', [
            'pageTitle' => (string) $album['name'],
            'album'     => $album,
            'images'    => $result['items'],
            'pager'     => $result,
        ]);
    }



    public function share(string $key): HttpResponse
    {
        $album = Album::findByKey($key);
        if ($album === null || (int) $album['is_public'] !== 1) {
            abort(404, '分享链接不存在或已关闭');
        }

        if (!ShareService::granted('album', (int) $album['id'], $album, false)) {
            return $this->view('share_lock', [
                'pageTitle' => '需要访问密码',
                'kind'      => '相册',
                'action'    => url('s/' . $key),
                'backUrl'   => url('explore'),
                'name'      => (string) $album['name'],
            ], 'layouts/site');
        }

        $page = max(1, Request::int('page', 1));
        $result = Image::paginate([
            'album_id' => (int) $album['id'],
            'user_id'  => (int) $album['user_id'],
            'status'   => Image::STATUS_APPROVED,
        ], $page, 48);

        return $this->view('share', [
            'pageTitle' => (string) $album['name'],
            'album'     => $album,
            'images'    => $result['items'],
            'pager'     => $result,
        ], 'layouts/blank');
    }

    /**
     * 访客提交相册分享密码
     */
    public function unlockShare(string $key): void
    {
        $this->validateCsrf();

        $album = Album::findByKey($key);
        if ($album === null || (int) $album['is_public'] !== 1) {
            abort(404, '分享链接不存在或已关闭');
        }

        $guard = '_share_try:album:' . (int) $album['id'];
        $tries = (int) Session::get($guard . '.n', 0);
        $until = (int) Session::get($guard . '.t', 0);
        if ($tries >= 5 && time() < $until) {
            flash('error', '尝试次数过多，请 ' . max(1, (int) ceil(($until - time()) / 60)) . ' 分钟后再试');
            redirect('s/' . $key);
        }

        $password = (string) Request::input('password', '');
        if (!ShareService::unlock('album', (int) $album['id'], $album, $password)) {
            Session::put($guard, ['n' => $tries + 1, 't' => time() + 300]);
            flash('error', '密码不正确');
            redirect('s/' . $key);
        }

        Session::forget($guard);
        redirect('s/' . $key);
    }

    private function payload(array $album): array
    {
        return [
            'id'        => (int) ($album['id'] ?? 0),
            'name'      => (string) ($album['name'] ?? ''),
            'intro'     => (string) ($album['intro'] ?? ''),
            'image_num' => (int) ($album['image_num'] ?? 0),
            'is_public' => (int) ($album['is_public'] ?? 0),
            'locked'    => trim((string) ($album['password'] ?? '')) !== '',
            'key'       => (string) ($album['key'] ?? ''),
            'share_url' => ((int) ($album['is_public'] ?? 0) === 1 && !empty($album['key'])) ? url('s/' . $album['key']) : '',
        ];
    }
}
