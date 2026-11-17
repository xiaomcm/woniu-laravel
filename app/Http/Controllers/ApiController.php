<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Database as DB;
use App\Core\Request;
use App\Core\Setting;
use App\Models\Album;
use App\Models\Image;
use App\Models\Token;
use App\Models\User;
use App\Services\Links;
use App\Services\UploadException;
use App\Services\UploadService;

class ApiController extends Controller
{
    private function authenticate(): array
    {
        // 全局开关：站主在后台关掉后，所有需要登录的接口一律停服。
        // 放在认证最前面，令牌有效也一样挡住，避免出现「关了还能用旧令牌」的错觉。
        if (!Setting::bool('api_enabled', true)) {
            $this->api(false, '站点已关闭 API 接口', null, 403);
        }

        $plain = Request::bearer();
        if ($plain !== null && $plain !== '') {
            $token = Token::findByPlain($plain);
            if ($token === null) {
                $this->api(false, 'Token 无效或已过期', null, 401);
            }
            Token::touchById((int) $token['id']);
            $user = User::find((int) $token['user_id']);
            if ($user === null || (int) $user['status'] !== 1) {
                $this->api(false, '账号不可用', null, 401);
            }
            // 用户自己关了 API：令牌仍然有效，但调不通任何需要认证的接口
            if (array_key_exists('api_enabled', $user) && (int) $user['api_enabled'] !== 1) {
                $this->api(false, '该账号已关闭 API 接口', null, 403);
            }
            return $user;
        }

        $user = Auth::user();
        if ($user !== null) {
            if (array_key_exists('api_enabled', $user) && (int) $user['api_enabled'] !== 1) {
                $this->api(false, '该账号已关闭 API 接口', null, 403);
            }
            return $user;
        }
        $this->api(false, '未提供有效的 API Token', null, 401);
        exit;
    }

    public function profile(): void
    {
        $user = $this->authenticate();
        $this->api(true, '', $this->profilePayload($user));
    }

    private function profilePayload(array $user): array
    {
        $group = \App\Models\Group::find((int) $user['group_id']);
        return [
            'id'           => (int) $user['id'],
            'name'         => (string) $user['name'],
            'email'        => (string) $user['email'],
            'is_admin'     => (int) $user['is_admin'] === 1,
            'group'        => (string) ($group['name'] ?? ''),
            'capacity'     => User::capacity($user),
            'used'         => User::usedBytes((int) $user['id']),
            'image_num'    => (int) DB::value(
                'SELECT COUNT(*) FROM ' . DB::table('images') . ' WHERE user_id = :uid AND deleted_at IS NULL',
                ['uid' => (int) $user['id']]
            ),
            'registered_at' => (string) $user['created_at'],
        ];
    }

    public function upload(): void
    {
        // 全局开关与用户开关已在 authenticate() 里统一检查
        $user = $this->authenticate();

        $files = Request::files('files', 'file');
        $url = (string) Request::input('url', '');
        if ($files === [] && $url === '') {
            $this->api(false, '没有收到文件');
        }

        $albumId = Request::int('album_id');
        if ($albumId > 0) {
            $album = Album::find($albumId);
            if ($album === null || (int) $album['user_id'] !== (int) $user['id']) {
                $this->api(false, '相册不存在');
            }
        }

        $service = new UploadService($user, $albumId, Request::int('storage_id', 0));
        $items = [];
        $errors = [];

        if ($files === [] && $url !== '') {
            try {
                $items[] = Links::apiPayload($service->uploadFromUrl($url));
            } catch (UploadException $e) {
                $errors[] = $e->getMessage();
            }
        }
        foreach ($files as $file) {
            try {
                $items[] = Links::apiPayload($service->upload($file));
            } catch (UploadException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($items === []) {
            $this->api(false, implode('；', $errors));
        }
        $this->api(true, $errors === [] ? '上传成功' : implode('；', $errors), count($items) === 1 ? $items[0] : $items);
    }

    public function images(): void
    {
        $user = $this->authenticate();
        $page = max(1, Request::int('page', 1));
        $perPage = max(1, min(100, Request::int('per_page', 20)));
        $result = Image::paginate([
            'user_id'  => (int) $user['id'],
            'album_id' => Request::input('album_id') === null ? null : Request::int('album_id'),
            'keyword'  => (string) Request::input('keyword', ''),
        ], $page, $perPage);

        $items = array_map([Links::class, 'apiPayload'], $result['items']);
        $this->api(true, '', [
            'data'         => $items,
            'current_page' => $result['page'],
            'last_page'    => $result['last_page'],
            'per_page'     => $result['per_page'],
            'total'        => $result['total'],
        ]);
    }

    public function image(string $id): void
    {
        $user = $this->authenticate();
        $image = Image::findForUser((int) $id, (int) $user['id']);
        if ($image === null) {
            $this->api(false, '图片不存在', null, 404);
        }
        $this->api(true, '', Links::apiPayload($image));
    }

    public function destroy(string $id): void
    {
        $user = $this->authenticate();
        $image = Image::findForUser((int) $id, (int) $user['id']);
        if ($image === null) {
            $this->api(false, '图片不存在', null, 404);
        }
        Image::softDelete((int) $id);
        Album::recount((int) $image['album_id']);
        $this->api(true, '已删除');
    }

    public function albums(): void
    {
        $user = $this->authenticate();
        $items = [];
        foreach (Album::allForUser((int) $user['id']) as $album) {
            $items[] = $this->albumPayload($album);
        }
        $this->api(true, '', $items);
    }

    public function stats(): void
    {
        $user = $this->authenticate();
        $uid = (int) $user['id'];
        $group = \App\Models\Group::find((int) $user['group_id']);

        $this->api(true, '', [
            'capacity'     => User::capacity($user),
            'used'         => User::usedBytes($uid),
            'image_num'    => (int) DB::value(
                'SELECT COUNT(*) FROM ' . DB::table('images') . ' WHERE user_id = :uid AND deleted_at IS NULL',
                ['uid' => $uid]
            ),
            'album_num'    => (int) DB::value(
                'SELECT COUNT(*) FROM ' . DB::table('albums') . ' WHERE user_id = :uid',
                ['uid' => $uid]
            ),
            'today_upload' => (int) DB::value(
                'SELECT COUNT(*) FROM ' . DB::table('images') . ' WHERE user_id = :uid AND deleted_at IS NULL AND created_at >= :t',
                ['uid' => $uid, 't' => date('Y-m-d 00:00:00')]
            ),
            'views'        => (int) DB::value(
                'SELECT COALESCE(SUM(views),0) FROM ' . DB::table('images') . ' WHERE user_id = :uid',
                ['uid' => $uid]
            ),
            'group'        => (string) ($group['name'] ?? ''),
            'daily_limit'  => (int) ($group['daily_limit'] ?? 0),
        ]);
    }

    public function updateProfile(): void
    {
        $user = $this->authenticate();
        $data = [];

        $name = trim((string) Request::input('name', ''));
        if ($name !== '') {
            if (mb_strlen($name) < 2 || mb_strlen($name) > 20) {
                $this->api(false, '昵称长度需在 2-20 字之间');
            }
            $data['name'] = $name;
        }
        $intro = Request::input('intro');
        if ($intro !== null) {
            $data['intro'] = mb_substr((string) $intro, 0, 200);
        }
        if ($data === []) {
            $this->api(false, '没有需要更新的字段');
        }
        $data['updated_at'] = now();
        DB::update('users', $data, 'id = :id', ['id' => (int) $user['id']]);
        $this->api(true, '已保存');
    }

    public function updateImage(string $id): void
    {
        $user = $this->authenticate();
        $image = Image::findForUser((int) $id, (int) $user['id']);
        if ($image === null) {
            $this->api(false, '图片不存在', null, 404);
        }

        $data = [];
        $name = trim((string) Request::input('name', ''));
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
        if (Request::input('is_public') !== null) {
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
        if ($data === []) {
            $this->api(false, '没有需要更新的字段');
        }

        $data['updated_at'] = now();
        DB::update('images', $data, 'id = :id', ['id' => (int) $id]);
        Album::recount((int) $image['album_id']);
        if (!empty($data['album_id'])) {
            Album::recount((int) $data['album_id']);
        }

        $fresh = Image::find((int) $id) ?? $image;
        $this->api(true, '已保存', Links::apiPayload($fresh));
    }

    public function moveImage(string $id): void
    {
        $user = $this->authenticate();
        $image = Image::findForUser((int) $id, (int) $user['id']);
        if ($image === null) {
            $this->api(false, '图片不存在', null, 404);
        }

        $albumId = Request::int('album_id');
        if ($albumId > 0) {
            $album = Album::find($albumId);
            if ($album === null || (int) $album['user_id'] !== (int) $user['id']) {
                $this->api(false, '相册不存在');
            }
        }

        DB::update('images', [
            'album_id'   => $albumId > 0 ? $albumId : null,
            'updated_at' => now(),
        ], 'id = :id', ['id' => (int) $id]);

        Album::recount((int) $image['album_id']);
        if ($albumId > 0) {
            Album::recount($albumId);
        }
        $this->api(true, '已移动');
    }

    public function restoreImage(string $id): void
    {
        $user = $this->authenticate();
        $image = DB::fetch(
            'SELECT * FROM ' . DB::table('images') . ' WHERE id = :id AND user_id = :uid',
            ['id' => (int) $id, 'uid' => (int) $user['id']]
        );
        if ($image === null) {
            $this->api(false, '图片不存在', null, 404);
        }
        DB::update('images', ['deleted_at' => null, 'updated_at' => now()], 'id = :id', ['id' => (int) $id]);
        Album::recount($image['album_id'] === null ? null : (int) $image['album_id']);
        $this->api(true, '已恢复');
    }

    public function album(string $id): void
    {
        $user = $this->authenticate();
        $album = Album::find((int) $id);
        if ($album === null || (int) $album['user_id'] !== (int) $user['id']) {
            $this->api(false, '相册不存在', null, 404);
        }
        $this->api(true, '', $this->albumPayload($album));
    }

    public function albumStore(): void
    {
        $user = $this->authenticate();

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

        $password = trim((string) Request::input('password', ''));
        if ($password !== '') {
            Album::updateById($id, ['password' => \App\Services\ShareService::hash($password)]);
        }

        $album = Album::find($id);
        $this->api(true, '创建成功', $album === null ? null : $this->albumPayload($album));
    }

    public function albumUpdate(string $id): void
    {
        $user = $this->authenticate();
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
        $password = trim((string) Request::input('password', ''));
        if ($password !== '') {
            $data['password'] = \App\Services\ShareService::hash($password);
        } elseif (Request::bool('clear_password', false)) {
            $data['password'] = null;
        }

        if ($data === []) {
            $this->api(false, '没有需要更新的字段');
        }
        Album::updateById((int) $id, $data);
        $fresh = Album::find((int) $id);
        $this->api(true, '已保存', $fresh === null ? null : $this->albumPayload($fresh));
    }

    public function albumDelete(string $id): void
    {
        $user = $this->authenticate();
        $album = Album::find((int) $id);
        if ($album === null || (int) $album['user_id'] !== (int) $user['id']) {
            $this->api(false, '相册不存在', null, 404);
        }
        Album::deleteById((int) $id);
        $this->api(true, '已删除，相册内图片已移动到未分类');
    }

    public function tokens(): void
    {
        $user = $this->authenticate();
        $rows = DB::fetchAll(
            'SELECT id, name, last_used_at, expires_at, created_at FROM ' . DB::table('tokens') . '
             WHERE user_id = :uid ORDER BY id DESC',
            ['uid' => (int) $user['id']]
        );
        $this->api(true, '', $rows);
    }

    public function tokenStore(): void
    {
        $user = $this->authenticate();

        $name = trim((string) Request::input('name', 'API Token'));
        $name = $name === '' ? 'API Token' : mb_substr($name, 0, 60);
        $plain = Token::create(
            (int) $user['id'],
            $name,
            Request::input('expires_at') === null ? null : (string) Request::input('expires_at')
        );

        // 明文只在创建时返回一次
        $this->api(true, 'Token 已创建，请立即保存', [
            'name'  => $name,
            'token' => $plain,
        ]);
    }

    public function tokenDelete(string $id): void
    {
        $user = $this->authenticate();
        $row = DB::fetch(
            'SELECT id FROM ' . DB::table('tokens') . ' WHERE id = :id AND user_id = :uid',
            ['id' => (int) $id, 'uid' => (int) $user['id']]
        );
        if ($row === null) {
            $this->api(false, 'Token 不存在', null, 404);
        }
        DB::delete('tokens', 'id = :id', ['id' => (int) $id]);
        $this->api(true, '已删除');
    }

    public function packages(): void
    {
        if (!Setting::bool('shop_enabled', false)) {
            $this->api(false, '商城未开放', null, 404);
        }
        $items = [];
        foreach (\App\Models\Package::allRows(true) as $package) {
            $items[] = $this->packagePayload($package);
        }
        $this->api(true, '', $items);
    }

    public function package(string $id): void
    {
        $package = \App\Models\Package::find((int) $id);
        if ($package === null || (int) $package['status'] !== 1) {
            $this->api(false, '套餐不存在', null, 404);
        }
        $this->api(true, '', $this->packagePayload($package));
    }

    public function orders(): void
    {
        $user = $this->authenticate();
        $items = [];
        foreach (\App\Models\Order::recentForUser((int) $user['id'], 50) as $order) {
            $items[] = $this->orderPayload($order);
        }
        $this->api(true, '', $items);
    }

    public function order(string $no): void
    {
        $user = $this->authenticate();
        $order = \App\Models\Order::findByNo($no);
        if ($order === null || (int) $order['user_id'] !== (int) $user['id']) {
            $this->api(false, '订单不存在', null, 404);
        }
        $this->api(true, '', $this->orderPayload($order));
    }

    public function orderStore(): void
    {
        $user = $this->authenticate();

        if (!Setting::bool('shop_enabled', false)) {
            $this->api(false, '商城未开放', null, 404);
        }

        $result = \App\Services\PaymentService::createOrder(
            $user,
            Request::int('package_id'),
            Request::int('driver_id'),
            trim((string) Request::input('coupon', ''))
        );

        if (!$result['ok']) {
            $this->api(false, $result['message']);
        }

        $this->api(true, $result['message'], [
            'order' => $result['order'] === null ? null : $this->orderPayload($result['order']),
            'pay'   => $result['pay'],
        ]);
    }

    public function notices(): void
    {
        $items = [];
        foreach (\App\Models\Notice::active(20) as $notice) {
            $items[] = [
                'id'         => (int) $notice['id'],
                'title'      => (string) $notice['title'],
                'content'    => (string) $notice['content'],
                'level'      => (string) $notice['level'],
                'pinned'     => (int) $notice['pinned'] === 1,
                'created_at' => (string) $notice['created_at'],
                'expire_at'  => (string) ($notice['expire_at'] ?? ''),
            ];
        }
        $this->api(true, '', $items);
    }

    public function pages(): void
    {
        $items = [];
        foreach (\App\Models\Page::allRows(true) as $page) {
            $items[] = [
                'id'    => (int) $page['id'],
                'title' => (string) $page['title'],
                'slug'  => (string) $page['slug'],
                'url'   => url('p/' . (string) $page['slug']),
            ];
        }
        $this->api(true, '', $items);
    }

    public function page(string $slug): void
    {
        $page = \App\Models\Page::findBySlug($slug);
        if ($page === null || (int) $page['status'] !== 1) {
            $this->api(false, '页面不存在', null, 404);
        }
        $this->api(true, '', [
            'id'         => (int) $page['id'],
            'title'      => (string) $page['title'],
            'slug'       => (string) $page['slug'],
            'content'    => (string) $page['content'],
            'updated_at' => (string) $page['updated_at'],
        ]);
    }

    public function tickets(): void
    {
        $user = $this->authenticate();
        $page = max(1, Request::int('page', 1));
        $result = \App\Models\Ticket::paginate(['user_id' => (int) $user['id']], $page, 20);

        $this->api(true, '', [
            'data'         => $result['items'],
            'current_page' => $result['page'],
            'last_page'    => $result['last_page'],
            'total'        => $result['total'],
        ]);
    }

    public function ticketStore(): void
    {
        $user = $this->authenticate();

        if (!Setting::bool('ticket_enabled', true)) {
            $this->api(false, '工单未开放', null, 404);
        }

        $subject = trim((string) Request::input('subject', ''));
        $content = trim((string) Request::input('content', ''));
        if (mb_strlen($subject) < 4) {
            $this->api(false, '标题至少 4 个字');
        }
        if (mb_strlen($content) < 10) {
            $this->api(false, '问题描述至少 10 个字');
        }

        $id = \App\Models\Ticket::create(
            (int) $user['id'],
            $subject,
            (string) Request::input('category', 'other'),
            Request::int('priority', 1),
            $content
        );
        $this->api(true, '工单已提交', ['id' => $id]);
    }

    public function ticket(string $id): void
    {
        $user = $this->authenticate();
        $ticket = \App\Models\Ticket::findForUser((int) $id, (int) $user['id']);
        if ($ticket === null) {
            $this->api(false, '工单不存在', null, 404);
        }
        $this->api(true, '', [
            'ticket'  => $ticket,
            'replies' => \App\Models\Ticket::replies((int) $id),
        ]);
    }

    public function ticketReply(string $id): void
    {
        $user = $this->authenticate();
        $ticket = \App\Models\Ticket::findForUser((int) $id, (int) $user['id']);
        if ($ticket === null) {
            $this->api(false, '工单不存在', null, 404);
        }
        if ((int) $ticket['status'] === \App\Models\Ticket::STATUS_CLOSED) {
            $this->api(false, '工单已关闭');
        }
        $content = trim((string) Request::input('content', ''));
        if ($content === '') {
            $this->api(false, '内容不能为空');
        }
        \App\Models\Ticket::reply((int) $id, (int) $user['id'], false, $content);
        $this->api(true, '已发送');
    }

    public function storages(): void
    {
        $user = $this->authenticate();
        $rows = \App\Models\Group::storages((int) $user['group_id']);
        $items = [];
        foreach ($rows as $storage) {
            $items[] = [
                'id'       => (int) $storage['id'],
                'name'     => (string) $storage['name'],
                //既有实现 这里取的是 storages 表不存在的列，PHP 警告被吞了返回空串，这里保持一致
                'driver'   => (string) ($storage['driver'] ?? ''),
                'is_default' => (int) $storage['is_default'] === 1,
            ];
        }
        $this->api(true, '', $items);
    }

    public function drivers(): void
    {
        $payments = [];
        foreach (\App\Models\Driver::active('payment') as $driver) {
            $payments[] = [
                'id'       => (int) $driver['id'],
                'name'     => (string) $driver['name'],
                'provider' => (string) $driver['provider'],
            ];
        }
        $socials = [];
        foreach (\App\Models\Driver::active('social') as $driver) {
            $socials[] = [
                'id'       => (int) $driver['id'],
                'name'     => (string) $driver['name'],
                'provider' => (string) $driver['provider'],
                'url'      => url('oauth/' . (int) $driver['id']),
            ];
        }

        $this->api(true, '', [
            'payments' => $payments,
            'socials'  => $socials,
            'currency' => (string) Setting::get('currency_symbol', '¥'),
        ]);
    }

    private function albumPayload(array $album): array
    {
        return [
            'id'        => (int) $album['id'],
            'name'      => (string) $album['name'],
            'intro'     => (string) $album['intro'],
            'image_num' => (int) $album['image_num'],
            'is_public' => (int) $album['is_public'],
            'locked'    => trim((string) ($album['password'] ?? '')) !== '',
            'key'       => (string) $album['key'],
            'share_url' => ((int) $album['is_public'] === 1 && $album['key']) ? url('s/' . $album['key']) : '',
            'created_at' => (string) $album['created_at'],
        ];
    }

    private function packagePayload(array $package): array
    {
        $durationDays = (int) $package['duration_days'];
        $purchaseLimit = (int) ($package['purchase_limit'] ?? 0);
        // 单文件/每日张数读套餐绑定用户组的配置（买后真正生效的值），
        // 跟套餐页展示、上传校验保持同一份数据源。
        $group = \App\Models\Group::find((int) ($package['group_id'] ?? 0));
        $maxFileSize = $group === null ? 0 : (int) ($group['max_file_size'] ?? 0);
        $dailyLimit = $group === null ? 0 : (int) ($group['daily_limit'] ?? 0);

        return [
            'id'             => (int) $package['id'],
            'name'           => (string) $package['name'],
            'description'    => (string) ($package['description'] ?? ''),
            'price_cents'    => (int) $package['price_cents'],
            'price_text'     => format_money((int) $package['price_cents']),
            'duration_days'  => $durationDays,
            // duration_days = 0 表示永久有效（不限制）
            'is_permanent'   => $durationDays <= 0,
            'duration_text'  => $durationDays > 0 ? $durationDays . ' 天' : '永久',
            // 限购：purchase_limit = 0 表示不限购；N 表示每个用户最多购买 N 次
            'purchase_limit'      => $purchaseLimit,
            'is_limit_purchase'   => $purchaseLimit > 0,
            'purchase_limit_text' => $purchaseLimit > 0 ? '限购 ' . $purchaseLimit . ' 次' : '不限',
            'capacity'       => (int) $package['capacity'],
            'max_file_size'  => $maxFileSize,
            'daily_limit'    => $dailyLimit,
            'features'       => $package['features_list'] ?? [],
        ];
    }

    private function orderPayload(array $order): array
    {
        return [
            'order_no'     => (string) $order['order_no'],
            'package_id'   => (int) $order['package_id'],
            'package_name' => (string) $order['package_name'],
            'amount_cents' => (int) $order['amount_cents'],
            'discount_cents' => (int) $order['discount_cents'],
            'total_cents'  => (int) $order['total_cents'],
            'total_text'   => (string) $order['total_text'],
            'status'       => (int) $order['status'],
            'status_text'  => (string) $order['status_text'],
            'paid_at'      => (string) ($order['paid_at'] ?? ''),
            'expire_at'    => (string) ($order['expire_at'] ?? ''),
            'created_at'   => (string) $order['created_at'],
        ];
    }

    // ===================== API 扩展（补齐至 100+ 端点） =====================

    private function paged(string $table, string $where, array $params, int $page, int $perPage, string $columns = '*'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $t = DB::table($table);
        $total = (int) DB::value("SELECT COUNT(*) FROM {$t} WHERE {$where}", $params);
        $offset = ($page - 1) * $perPage;
        $items = DB::fetchAll("SELECT {$columns} FROM {$t} WHERE {$where} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}", $params);
        return [
            'data'         => $items,
            'current_page' => $page,
            'per_page'     => $perPage,
            'total'        => $total,
            'last_page'    => max(1, (int) ceil($total / $perPage)),
        ];
    }

    private function requireAdmin(array $user): void
    {
        if ((int) $user['is_admin'] !== 1) {
            $this->api(false, '需要管理员权限', null, 403);
        }
    }

    // ---- 账号 ----
    public function me(): void
    {
        $user = $this->authenticate();
        $this->api(true, '', $this->profilePayload($user));
    }

    public function userQuota(): void
    {
        $user = $this->authenticate();
        $uid = (int) $user['id'];
        $capacity = User::capacity($user);
        $used = User::usedBytes($uid);
        $this->api(true, '', [
            'capacity'   => $capacity,
            'used'       => $used,
            'remaining'  => max(0, $capacity - $used),
            'image_num'  => (int) DB::value('SELECT COUNT(*) FROM ' . DB::table('images') . ' WHERE user_id = :u AND deleted_at IS NULL', ['u' => $uid]),
            'daily_used' => (int) DB::value('SELECT COUNT(*) FROM ' . DB::table('images') . ' WHERE user_id = :u AND deleted_at IS NULL AND created_at >= :t', ['u' => $uid, 't' => date('Y-m-d 00:00:00')]),
        ]);
    }

    public function changePassword(): void
    {
        $user = $this->authenticate();
        $current = (string) Request::input('current_password', '');
        $new = (string) Request::input('password', '');
        if (!password_verify($current, (string) $user['password'])) {
            $this->api(false, '当前密码不正确');
        }
        if (mb_strlen($new) < 6) {
            $this->api(false, '新密码至少 6 位');
        }
        DB::update('users', ['password' => password_hash($new, PASSWORD_DEFAULT), 'updated_at' => now()], 'id = :id', ['id' => (int) $user['id']]);
        $this->api(true, '密码已更新');
    }

    public function changeEmail(): void
    {
        $user = $this->authenticate();
        if (!password_verify((string) Request::input('password', ''), (string) $user['password'])) {
            $this->api(false, '密码不正确');
        }
        $email = strtolower(trim((string) Request::input('email', '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->api(false, '邮箱格式不正确');
        }
        if (DB::fetch('SELECT id FROM ' . DB::table('users') . ' WHERE email = :e AND id <> :id', ['e' => $email, 'id' => (int) $user['id']]) !== null) {
            $this->api(false, '该邮箱已被占用');
        }
        DB::update('users', ['email' => $email, 'updated_at' => now()], 'id = :id', ['id' => (int) $user['id']]);
        $this->api(true, '邮箱已更新');
    }

    public function userAvatar(): void
    {
        $user = $this->authenticate();
        if (trim((string) ($user['avatar'] ?? '')) === '') {
            $this->api(false, '未设置头像', null, 404);
        }
        $this->api(true, '', ['avatar' => (string) $user['avatar']]);
    }

    public function userLogs(): void
    {
        $user = $this->authenticate();
        $res = $this->paged('logs', 'user_id = :u', ['u' => (int) $user['id']], max(1, Request::int('page', 1)), 20, 'id, action, ip, detail, created_at');
        $this->api(true, '', $res);
    }

    public function userOauth(): void
    {
        $user = $this->authenticate();
        $rows = DB::fetchAll('SELECT id, provider, nickname, avatar, created_at FROM ' . DB::table('user_oauth') . ' WHERE user_id = :u ORDER BY id DESC', ['u' => (int) $user['id']]);
        $this->api(true, '', $rows);
    }

    public function unbindOauth(string $id): void
    {
        $user = $this->authenticate();
        DB::delete('user_oauth', 'id = :id AND user_id = :u', ['id' => (int) $id, 'u' => (int) $user['id']]);
        $this->api(true, '已解绑');
    }

    // ---- 图片 ----
    public function imagesTrash(): void
    {
        $user = $this->authenticate();
        $res = $this->paged('images', 'user_id = :u AND deleted_at IS NOT NULL', ['u' => (int) $user['id']], max(1, Request::int('page', 1)), 20, 'id, name, url, size, width, height, deleted_at, created_at');
        $res['data'] = array_map([Links::class, 'apiPayload'], $res['data']);
        $this->api(true, '', $res);
    }

    public function imagesBatchDelete(): void
    {
        $user = $this->authenticate();
        $ids = $this->ids();
        if ($ids === []) {
            $this->api(false, '缺少 ids');
        }
        $count = 0;
        foreach ($ids as $id) {
            $img = Image::findForUser($id, (int) $user['id']);
            if ($img !== null) {
                Image::softDelete($id);
                Album::recount($img['album_id'] === null ? null : (int) $img['album_id']);
                $count++;
            }
        }
        $this->api(true, "已删除 {$count} 张");
    }

    public function imagesBatchMove(): void
    {
        $user = $this->authenticate();
        $ids = $this->ids();
        $albumId = Request::int('album_id');
        if ($albumId > 0) {
            $album = Album::find($albumId);
            if ($album === null || (int) $album['user_id'] !== (int) $user['id']) {
                $this->api(false, '相册不存在');
            }
        }
        $count = 0;
        foreach ($ids as $id) {
            if (Image::findForUser($id, (int) $user['id']) !== null) {
                DB::update('images', ['album_id' => $albumId > 0 ? $albumId : null, 'updated_at' => now()], 'id = :id', ['id' => $id]);
                $count++;
            }
        }
        if ($albumId > 0) {
            Album::recount($albumId);
        }
        $this->api(true, "已移动 {$count} 张");
    }

    public function imageMeta(string $id): void
    {
        $user = $this->authenticate();
        $img = Image::findForUser((int) $id, (int) $user['id']);
        if ($img === null) {
            $this->api(false, '图片不存在', null, 404);
        }
        $this->api(true, '', [
            'id'            => (int) $img['id'],
            'name'          => (string) $img['name'],
            'original_name' => (string) $img['original_name'],
            'description'   => (string) $img['description'],
            'tags'          => (string) $img['tags'],
            'mimetype'      => (string) $img['mimetype'],
            'extension'     => (string) $img['extension'],
            'size'          => (int) $img['size'],
            'width'         => (int) $img['width'],
            'height'        => (int) $img['height'],
            'md5'           => (string) $img['md5'],
            'sha1'          => (string) $img['sha1'],
            'views'         => (int) $img['views'],
            'is_public'     => (int) $img['is_public'],
            'url'           => (string) $img['url'],
            'thumb_url'     => (string) $img['thumb_url'],
            'created_at'    => (string) $img['created_at'],
        ]);
    }

    public function imagesSearch(): void
    {
        $user = $this->authenticate();
        $keyword = trim((string) Request::input('keyword', ''));
        if ($keyword === '') {
            $this->api(false, '请输入关键词');
        }
        $like = '%' . DB::like($keyword) . '%';
        $res = $this->paged(
            'images',
            'user_id = :u AND deleted_at IS NULL AND (name LIKE :k OR description LIKE :k2 OR tags LIKE :k3)',
            ['u' => (int) $user['id'], 'k' => $like, 'k2' => $like, 'k3' => $like],
            max(1, Request::int('page', 1)),
            20
        );
        $res['data'] = array_map([Links::class, 'apiPayload'], $res['data']);
        $this->api(true, '', $res);
    }

    public function imagesRandom(): void
    {
        $user = $this->authenticate();
        $row = DB::fetch('SELECT MIN(id) m, MAX(id) x FROM ' . DB::table('images') . ' WHERE user_id = :u AND deleted_at IS NULL', ['u' => (int) $user['id']]);
        if ($row === null || $row['m'] === null) {
            $this->api(false, '暂无可用的图片');
        }
        $rand = random_int((int) $row['m'], (int) $row['x']);
        $img = DB::fetch('SELECT * FROM ' . DB::table('images') . ' WHERE user_id = :u AND id >= :r AND deleted_at IS NULL ORDER BY id ASC LIMIT 1', ['u' => (int) $user['id'], 'r' => $rand]);
        if ($img === null) {
            $this->api(false, '暂无可用的图片');
        }
        $this->api(true, '', Links::apiPayload($img));
    }

    public function imagesStat(): void
    {
        $user = $this->authenticate();
        $uid = (int) $user['id'];
        $days = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $next = date('Y-m-d', strtotime("-{$i} days +1 day"));
            $cnt = (int) DB::value(
                'SELECT COUNT(*) FROM ' . DB::table('images') . ' WHERE user_id = :u AND deleted_at IS NULL AND created_at >= :s AND created_at < :e',
                ['u' => $uid, 's' => $day . ' 00:00:00', 'e' => $next . ' 00:00:00']
            );
            $days[] = ['date' => $day, 'count' => $cnt];
        }
        $this->api(true, '', [
            'per_day' => $days,
            'total'   => (int) DB::value('SELECT COUNT(*) FROM ' . DB::table('images') . ' WHERE user_id = :u AND deleted_at IS NULL', ['u' => $uid]),
        ]);
    }

    public function imageDownload(string $id): void
    {
        $user = $this->authenticate();
        $img = Image::findForUser((int) $id, (int) $user['id']);
        if ($img === null) {
            $this->api(false, '图片不存在', null, 404);
        }
        $this->api(true, '', ['url' => (string) $img['url'], 'name' => (string) $img['name']]);
    }

    public function imageCopy(string $id): void
    {
        $user = $this->authenticate();
        $img = Image::findForUser((int) $id, (int) $user['id']);
        if ($img === null) {
            $this->api(false, '图片不存在', null, 404);
        }
        $newId = DB::insert('images', [
            'user_id'       => (int) $user['id'],
            'album_id'      => $img['album_id'] === null ? null : (int) $img['album_id'],
            'storage_id'    => (int) $img['storage_id'],
            'name'          => (string) $img['name'] . ' 副本',
            'original_name' => (string) $img['original_name'],
            'description'   => (string) $img['description'],
            'tags'          => (string) $img['tags'],
            'pathname'      => (string) $img['pathname'],
            'mimetype'      => (string) $img['mimetype'],
            'extension'     => (string) $img['extension'],
            'size'          => (int) $img['size'],
            'md5'           => (string) $img['md5'],
            'sha1'          => (string) $img['sha1'],
            'width'         => (int) $img['width'],
            'height'        => (int) $img['height'],
            'url'           => (string) $img['url'],
            'thumb_url'     => (string) $img['thumb_url'],
            'views'         => 0,
            'status'        => 1,
            'is_public'     => (int) $img['is_public'],
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        if ($img['album_id'] !== null) {
            Album::recount((int) $img['album_id']);
        }
        $fresh = Image::find($newId);
        $this->api(true, '已复制', $fresh === null ? null : Links::apiPayload($fresh));
    }

    // ---- 相册 ----
    public function albumImages(string $id): void
    {
        $user = $this->authenticate();
        $album = Album::find((int) $id);
        if ($album === null || (int) $album['user_id'] !== (int) $user['id']) {
            $this->api(false, '相册不存在', null, 404);
        }
        $res = $this->paged('images', 'album_id = :a AND deleted_at IS NULL', ['a' => (int) $id], max(1, Request::int('page', 1)), 30);
        $res['data'] = array_map([Links::class, 'apiPayload'], $res['data']);
        $this->api(true, '', $res);
    }

    public function albumAddImage(string $id): void
    {
        $user = $this->authenticate();
        $album = Album::find((int) $id);
        if ($album === null || (int) $album['user_id'] !== (int) $user['id']) {
            $this->api(false, '相册不存在', null, 404);
        }
        $ids = $this->ids();
        if ($ids === []) {
            $this->api(false, '缺少图片 ids');
        }
        $count = 0;
        foreach ($ids as $imageId) {
            if (Image::findForUser($imageId, (int) $user['id']) !== null) {
                DB::update('images', ['album_id' => (int) $id, 'updated_at' => now()], 'id = :id', ['id' => $imageId]);
                $count++;
            }
        }
        Album::recount((int) $id);
        $this->api(true, "已加入 {$count} 张");
    }

    public function albumRemoveImage(string $id, string $imageId): void
    {
        $user = $this->authenticate();
        $album = Album::find((int) $id);
        if ($album === null || (int) $album['user_id'] !== (int) $user['id']) {
            $this->api(false, '相册不存在', null, 404);
        }
        $img = Image::findForUser((int) $imageId, (int) $user['id']);
        if ($img === null) {
            $this->api(false, '图片不存在', null, 404);
        }
        DB::update('images', ['album_id' => null, 'updated_at' => now()], 'id = :id', ['id' => (int) $imageId]);
        Album::recount((int) $id);
        $this->api(true, '已移出相册');
    }

    public function albumCover(string $id): void
    {
        $user = $this->authenticate();
        $album = Album::find((int) $id);
        if ($album === null || (int) $album['user_id'] !== (int) $user['id']) {
            $this->api(false, '相册不存在', null, 404);
        }
        $cover = trim((string) Request::input('cover', ''));
        if ($cover === '') {
            $first = DB::fetch('SELECT thumb_url, url FROM ' . DB::table('images') . ' WHERE album_id = :a AND deleted_at IS NULL ORDER BY id ASC LIMIT 1', ['a' => (int) $id]);
            $cover = $first === null ? '' : (string) ($first['thumb_url'] ?: $first['url']);
        }
        Album::updateById((int) $id, ['cover' => $cover === '' ? null : $cover]);
        $this->api(true, '封面已更新');
    }

    public function albumShare(string $key): void
    {
        $album = DB::fetch('SELECT * FROM ' . DB::table('albums') . ' WHERE `key` = :k AND is_public = 1', ['k' => $key]);
        if ($album === null) {
            $this->api(false, '相册不存在或已关闭分享', null, 404);
        }
        $imgs = DB::fetchAll('SELECT id, name, url, thumb_url, width, height, size, created_at FROM ' . DB::table('images') . ' WHERE album_id = :a AND deleted_at IS NULL ORDER BY id DESC LIMIT 100', ['a' => (int) $album['id']]);
        $this->api(true, '', [
            'id'      => (int) $album['id'],
            'name'    => (string) $album['name'],
            'intro'   => (string) $album['intro'],
            'images'  => $imgs,
        ]);
    }

    public function albumPassword(string $id): void
    {
        $user = $this->authenticate();
        $album = Album::find((int) $id);
        if ($album === null || (int) $album['user_id'] !== (int) $user['id']) {
            $this->api(false, '相册不存在', null, 404);
        }
        $password = trim((string) Request::input('password', ''));
        if ($password === '') {
            Album::updateById((int) $id, ['password' => null]);
            $this->api(true, '已取消密码');
        }
        Album::updateById((int) $id, ['password' => \App\Services\ShareService::hash($password)]);
        $this->api(true, '密码已设置');
    }

    // ---- Token ----
    public function tokenShow(string $id): void
    {
        $user = $this->authenticate();
        $row = DB::fetch('SELECT id, name, last_used_at, expires_at, created_at FROM ' . DB::table('tokens') . ' WHERE id = :id AND user_id = :u', ['id' => (int) $id, 'u' => (int) $user['id']]);
        if ($row === null) {
            $this->api(false, 'Token 不存在', null, 404);
        }
        $this->api(true, '', $row);
    }

    public function tokenRename(string $id): void
    {
        $user = $this->authenticate();
        if (DB::fetch('SELECT id FROM ' . DB::table('tokens') . ' WHERE id = :id AND user_id = :u', ['id' => (int) $id, 'u' => (int) $user['id']]) === null) {
            $this->api(false, 'Token 不存在', null, 404);
        }
        $name = trim((string) Request::input('name', ''));
        if ($name === '') {
            $this->api(false, '名称不能为空');
        }
        DB::update('tokens', ['name' => mb_substr($name, 0, 60)], 'id = :id', ['id' => (int) $id]);
        $this->api(true, '已重命名');
    }

    public function tokenRevoke(string $id): void
    {
        $user = $this->authenticate();
        DB::update('tokens', ['expires_at' => date('Y-m-d H:i:s')], 'id = :id AND user_id = :u', ['id' => (int) $id, 'u' => (int) $user['id']]);
        $this->api(true, '已吊销');
    }

    public function tokensRevokeAll(): void
    {
        $user = $this->authenticate();
        DB::update('tokens', ['expires_at' => date('Y-m-d H:i:s')], 'user_id = :u', ['u' => (int) $user['id']]);
        $this->api(true, '已吊销全部 Token');
    }

    // ---- 套餐 / 优惠券 / 订单 ----
    public function packageFeatures(string $id): void
    {
        $package = \App\Models\Package::find((int) $id);
        if ($package === null || (int) $package['status'] !== 1) {
            $this->api(false, '套餐不存在', null, 404);
        }
        $this->api(true, '', $this->packagePayload($package));
    }

    public function packagesActive(): void
    {
        $items = [];
        foreach (\App\Models\Package::allRows(true) as $package) {
            $items[] = $this->packagePayload($package);
        }
        $this->api(true, '', $items);
    }

    public function couponCheck(): void
    {
        $user = $this->authenticate();
        $code = trim((string) Request::input('code', ''));
        $amount = Request::int('amount_cents', 0);
        if ($code === '') {
            $this->api(false, '请输入优惠券码');
        }
        $check = \App\Models\Coupon::validate($code, (int) $user['id'], 0, $amount);
        if (!$check['ok']) {
            $this->api(false, $check['message']);
        }
        $this->api(true, $check['message'], [
            'code'          => $code,
            'discount_cents' => (int) $check['discount'],
            'final_cents'  => max(0, $amount - (int) $check['discount']),
        ]);
    }

    public function orderCancel(string $no): void
    {
        $user = $this->authenticate();
        $order = \App\Models\Order::findByNo($no);
        if ($order === null || (int) $order['user_id'] !== (int) $user['id']) {
            $this->api(false, '订单不存在', null, 404);
        }
        if ((int) $order['status'] !== \App\Models\Order::STATUS_PENDING) {
            $this->api(false, '仅待支付订单可取消');
        }
        \App\Models\Order::cancel((int) $order['id']);
        $this->api(true, '订单已取消');
    }

    public function orderPayGet(string $no): void
    {
        $user = $this->authenticate();
        $order = \App\Models\Order::findByNo($no);
        if ($order === null || (int) $order['user_id'] !== (int) $user['id']) {
            $this->api(false, '订单不存在', null, 404);
        }
        $this->api(true, '', $this->orderPayload($order));
    }

    public function orderPayPost(string $no): void
    {
        $user = $this->authenticate();
        $order = \App\Models\Order::findByNo($no);
        if ($order === null || (int) $order['user_id'] !== (int) $user['id']) {
            $this->api(false, '订单不存在', null, 404);
        }
        if ((int) $order['status'] !== \App\Models\Order::STATUS_PENDING) {
            $this->api(false, '订单不可支付');
        }
        $driverId = Request::int('driver_id', (int) $order['driver_id']);
        $driver = \App\Services\DriveService::paymentByDriverId($driverId);
        if ($driver === null) {
            $this->api(false, '支付通道不可用');
        }
        $pay = $driver->pay([
            'out_trade_no' => (string) $order['order_no'],
            'subject'      => (string) Setting::get('site_name', '蜗牛图床') . ' · ' . $order['package_name'],
            'amount_cents' => (int) $order['total_cents'],
            'notify_url'   => \App\Services\PaymentService::notifyUrl($driverId),
            'return_url'   => url('pay/return?order=' . $order['order_no']),
        ]);
        $this->api(true, '请完成支付', $pay);
    }

    public function ordersStat(): void
    {
        $user = $this->authenticate();
        $uid = (int) $user['id'];
        $t = DB::table('orders');
        $this->api(true, '', [
            'total'       => (int) DB::value("SELECT COUNT(*) FROM {$t} WHERE user_id = :u", ['u' => $uid]),
            'paid'        => (int) DB::value("SELECT COUNT(*) FROM {$t} WHERE user_id = :u AND status = :s", ['u' => $uid, 's' => \App\Models\Order::STATUS_PAID]),
            'pending'     => (int) DB::value("SELECT COUNT(*) FROM {$t} WHERE user_id = :u AND status = :s", ['u' => $uid, 's' => \App\Models\Order::STATUS_PENDING]),
            'spent_cents' => (int) DB::value("SELECT COALESCE(SUM(total_cents),0) FROM {$t} WHERE user_id = :u AND status = :s", ['u' => $uid, 's' => \App\Models\Order::STATUS_PAID]),
        ]);
    }

    // ---- 社会化登录 ----
    public function socials(): void
    {
        $items = [];
        foreach (\App\Models\Driver::active('social') as $driver) {
            $items[] = [
                'id'       => (int) $driver['id'],
                'name'     => (string) $driver['name'],
                'provider' => (string) $driver['provider'],
                'url'      => url('oauth/' . (int) $driver['id']),
            ];
        }
        $this->api(true, '', $items);
    }

    public function oauthUrl(string $id): void
    {
        $this->authenticate();
        $driver = \App\Models\Driver::find((int) $id);
        if ($driver === null || (string) $driver['type'] !== 'social') {
            $this->api(false, '登录方式不存在', null, 404);
        }
        $this->api(true, '', ['url' => url('oauth/' . (int) $id)]);
    }

    public function oauthBind(string $id): void
    {
        $this->authenticate();
        $driver = \App\Models\Driver::find((int) $id);
        if ($driver === null || (string) $driver['type'] !== 'social') {
            $this->api(false, '登录方式不存在', null, 404);
        }
        $this->api(true, '', ['url' => url('oauth/' . (int) $id)]);
    }

    public function oauthUnbind(string $id): void
    {
        $user = $this->authenticate();
        DB::delete('user_oauth', 'id = :id AND user_id = :u', ['id' => (int) $id, 'u' => (int) $user['id']]);
        $this->api(true, '已解绑');
    }

    // ---- 公告 / 页面 / 反馈 ----
    public function notice(string $id): void
    {
        $notice = \App\Models\Notice::find((int) $id);
        if ($notice === null || (int) $notice['status'] !== 1) {
            $this->api(false, '公告不存在', null, 404);
        }
        $this->api(true, '', [
            'id'         => (int) $notice['id'],
            'title'      => (string) $notice['title'],
            'content'    => (string) $notice['content'],
            'level'      => (string) $notice['level'],
            'pinned'     => (int) $notice['pinned'] === 1,
            'created_at' => (string) $notice['created_at'],
        ]);
    }

    public function feedbackSubmit(): void
    {
        $user = $this->authenticate();
        $content = trim((string) Request::input('content', ''));
        if (mb_strlen($content) < 5) {
            $this->api(false, '内容至少 5 个字');
        }
        DB::insert('feedbacks', [
            'user_id'    => (int) $user['id'],
            'kind'       => (string) Request::input('kind', 'suggestion'),
            'content'    => $content,
            'contact'    => (string) Request::input('contact', ''),
            'status'     => 0,
            'created_at' => now(),
        ]);
        $this->api(true, '已提交，感谢反馈');
    }

    public function feedbackList(): void
    {
        $user = $this->authenticate();
        $res = $this->paged('feedbacks', 'user_id = :u', ['u' => (int) $user['id']], max(1, Request::int('page', 1)), 20, 'id, kind, content, status, reply, created_at, replied_at');
        $this->api(true, '', $res);
    }

    public function feedbackShow(string $id): void
    {
        $user = $this->authenticate();
        $row = DB::fetch('SELECT id, kind, content, status, reply, created_at, replied_at FROM ' . DB::table('feedbacks') . ' WHERE id = :id AND user_id = :u', ['id' => (int) $id, 'u' => (int) $user['id']]);
        if ($row === null) {
            $this->api(false, '反馈不存在', null, 404);
        }
        $this->api(true, '', $row);
    }

    // ---- 工单 ----
    public function ticketReplies(string $id): void
    {
        $user = $this->authenticate();
        $ticket = \App\Models\Ticket::findForUser((int) $id, (int) $user['id']);
        if ($ticket === null) {
            $this->api(false, '工单不存在', null, 404);
        }
        $this->api(true, '', \App\Models\Ticket::replies((int) $id));
    }

    public function ticketClose(string $id): void
    {
        $user = $this->authenticate();
        $ticket = \App\Models\Ticket::findForUser((int) $id, (int) $user['id']);
        if ($ticket === null) {
            $this->api(false, '工单不存在', null, 404);
        }
        \App\Models\Ticket::close((int) $id);
        $this->api(true, '工单已关闭');
    }

    public function ticketReopen(string $id): void
    {
        $user = $this->authenticate();
        $ticket = \App\Models\Ticket::findForUser((int) $id, (int) $user['id']);
        if ($ticket === null) {
            $this->api(false, '工单不存在', null, 404);
        }
        \App\Models\Ticket::reopen((int) $id);
        $this->api(true, '工单已重新打开');
    }

    // ---- 举报 ----
    public function reportsList(): void
    {
        $user = $this->authenticate();
        $res = $this->paged('reports', 'reporter_id = :u', ['u' => (int) $user['id']], max(1, Request::int('page', 1)), 20);
        $this->api(true, '', $res);
    }

    public function reportSubmit(): void
    {
        $user = $this->authenticate();
        $imageId = Request::int('image_id', 0);
        $albumId = Request::int('album_id', 0);
        if ($imageId <= 0 && $albumId <= 0) {
            $this->api(false, '请指定举报的图片或相册');
        }
        if (trim((string) Request::input('reason', '')) === '') {
            $this->api(false, '请填写举报理由');
        }
        DB::insert('reports', [
            'image_id'    => $imageId > 0 ? $imageId : null,
            'album_id'    => $albumId > 0 ? $albumId : null,
            'reporter_id' => (int) $user['id'],
            'category'    => (string) Request::input('category', 'other'),
            'reason'      => (string) Request::input('reason', ''),
            'status'      => 0,
            'created_at'  => now(),
        ]);
        $this->api(true, '举报已提交，我们会尽快处理');
    }

    public function reportShow(string $id): void
    {
        $user = $this->authenticate();
        $row = DB::fetch('SELECT * FROM ' . DB::table('reports') . ' WHERE id = :id AND reporter_id = :u', ['id' => (int) $id, 'u' => (int) $user['id']]);
        if ($row === null) {
            $this->api(false, '举报不存在', null, 404);
        }
        $this->api(true, '', $row);
    }

    // ---- 日志（管理员） ----
    public function logsList(): void
    {
        $user = $this->authenticate();
        $this->requireAdmin($user);
        $res = $this->paged('logs', '1 = 1', [], max(1, Request::int('page', 1)), 30, 'id, user_id, action, ip, detail, created_at');
        $this->api(true, '', $res);
    }

    public function logShow(string $id): void
    {
        $user = $this->authenticate();
        $this->requireAdmin($user);
        $row = DB::fetch('SELECT * FROM ' . DB::table('logs') . ' WHERE id = :id', ['id' => (int) $id]);
        if ($row === null) {
            $this->api(false, '日志不存在', null, 404);
        }
        $this->api(true, '', $row);
    }

    // ---- 存储 / 用户组 / 驱动 ----
    public function storageShow(string $id): void
    {
        $this->authenticate();
        $row = DB::fetch('SELECT id, name, type, url, is_default, status, created_at, updated_at FROM ' . DB::table('storages') . ' WHERE id = :id', ['id' => (int) $id]);
        if ($row === null) {
            $this->api(false, '存储不存在', null, 404);
        }
        $this->api(true, '', $row);
    }

    public function storageTest(string $id): void
    {
        $user = $this->authenticate();
        $this->requireAdmin($user);
        $storage = \App\Models\Storage::find((int) $id);
        if ($storage === null) {
            $this->api(false, '存储不存在', null, 404);
        }
        $result = \App\Storage\Manager::test((int) $id);
        $this->api($result['ok'] ?? false, (string) ($result['message'] ?? ''), $result);
    }

    public function groupList(): void
    {
        $this->authenticate();
        $rows = DB::fetchAll('SELECT id, name, capacity, max_file_size, daily_limit, watermark FROM ' . DB::table('groups') . ' ORDER BY id ASC');
        $this->api(true, '', $rows);
    }

    public function groupShow(string $id): void
    {
        $this->authenticate();
        $row = DB::fetch('SELECT id, name, capacity, max_file_size, allowed_extensions, daily_limit, watermark FROM ' . DB::table('groups') . ' WHERE id = :id', ['id' => (int) $id]);
        if ($row === null) {
            $this->api(false, '用户组不存在', null, 404);
        }
        $this->api(true, '', $row);
    }

    public function driverShow(string $id): void
    {
        $this->authenticate();
        $row = DB::fetch('SELECT id, name, type, provider, status, created_at FROM ' . DB::table('drivers') . ' WHERE id = :id', ['id' => (int) $id]);
        if ($row === null) {
            $this->api(false, '驱动不存在', null, 404);
        }
        $this->api(true, '', $row);
    }

    public function regions(): void
    {
        $this->authenticate();
        $this->api(true, '', [
            'types'    => \App\Models\Storage::types(),
            'storages' => \App\Models\Storage::allRows(),
        ]);
    }

    public function storagesDefault(): void
    {
        $this->authenticate();
        $row = DB::fetch('SELECT id, name, type, url FROM ' . DB::table('storages') . ' WHERE is_default = 1 LIMIT 1');
        if ($row === null) {
            $this->api(false, '未设置默认存储', null, 404);
        }
        $this->api(true, '', $row);
    }

    // ---- 站点 / 公共 ----
    public function siteConfig(): void
    {
        $groups = [];
        foreach (\App\Models\Group::allRows() as $g) {
            $groups[] = ['id' => (int) $g['id'], 'name' => (string) $g['name']];
        }
        $storages = [];
        foreach (\App\Models\Storage::allRows() as $s) {
            $storages[] = ['id' => (int) $s['id'], 'name' => (string) $s['name']];
        }
        $this->api(true, '', [
            'site_name'        => (string) Setting::get('site_name', '蜗牛图床'),
            'registration_open' => Setting::bool('registration_open', true),
            'api_enabled'      => Setting::bool('api_enabled', true),
            'allow_upload'     => Setting::bool('allow_upload', true),
            'max_file_size'    => (int) Setting::get('max_file_size', 0),
            'allowed_extensions' => (string) Setting::get('allowed_extensions', ''),
            'groups'           => $groups,
            'storages'         => $storages,
        ]);
    }

    public function siteInfo(): void
    {
        $this->api(true, '', [
            'name'           => (string) Setting::get('site_name', '蜗牛图床'),
            'slogan'         => (string) Setting::get('slogan', ''),
            'open_register'  => Setting::bool('registration_open', true),
            'open_upload'    => Setting::bool('allow_upload', true),
        ]);
    }

    public function health(): void
    {
        $this->api(true, '', [
            'status'    => 'ok',
            'time'      => date('c'),
            'php'       => PHP_VERSION,
            'app'       => 'woniu-image-host',
        ]);
    }

    public function captchaStatus(): void
    {
        $meta = \App\Services\CaptchaService::frontend();
        $this->api(true, '', [
            'enabled'  => (bool) ($meta['enabled'] ?? false),
            'provider' => (string) ($meta['provider'] ?? ''),
            'mode'     => (string) ($meta['kind'] ?? 'none'),
        ]);
    }

    // ---- 统计 ----
    public function statsImages(): void
    {
        $user = $this->authenticate();
        $uid = (int) $user['id'];
        $t = DB::table('images');
        $this->api(true, '', [
            'total'     => (int) DB::value("SELECT COUNT(*) FROM {$t} WHERE user_id = :u AND deleted_at IS NULL", ['u' => $uid]),
            'size_sum'  => (int) DB::value("SELECT COALESCE(SUM(size),0) FROM {$t} WHERE user_id = :u AND deleted_at IS NULL", ['u' => $uid]),
            'views_sum' => (int) DB::value("SELECT COALESCE(SUM(views),0) FROM {$t} WHERE user_id = :u", ['u' => $uid]),
            'today'     => (int) DB::value("SELECT COUNT(*) FROM {$t} WHERE user_id = :u AND deleted_at IS NULL AND created_at >= :d", ['u' => $uid, 'd' => date('Y-m-d 00:00:00')]),
        ]);
    }

    public function statsAlbums(): void
    {
        $user = $this->authenticate();
        $uid = (int) $user['id'];
        $t = DB::table('albums');
        $this->api(true, '', [
            'total'       => (int) DB::value("SELECT COUNT(*) FROM {$t} WHERE user_id = :u", ['u' => $uid]),
            'public'      => (int) DB::value("SELECT COUNT(*) FROM {$t} WHERE user_id = :u AND is_public = 1", ['u' => $uid]),
            'image_total' => (int) DB::value("SELECT COALESCE(SUM(image_num),0) FROM {$t} WHERE user_id = :u", ['u' => $uid]),
        ]);
    }

    public function statsStorage(): void
    {
        $user = $this->authenticate();
        $capacity = User::capacity($user);
        $used = User::usedBytes((int) $user['id']);
        $this->api(true, '', [
            'capacity'  => $capacity,
            'used'      => $used,
            'percent'   => $capacity > 0 ? round($used / $capacity * 100, 2) : 0,
            'free'      => max(0, $capacity - $used),
        ]);
    }

    // ---- 管理员 ----
    public function adminStats(): void
    {
        $user = $this->authenticate();
        $this->requireAdmin($user);
        $this->api(true, '', [
            'users'   => (int) DB::value('SELECT COUNT(*) FROM ' . DB::table('users')),
            'images'  => (int) DB::value('SELECT COUNT(*) FROM ' . DB::table('images') . ' WHERE deleted_at IS NULL'),
            'albums'  => (int) DB::value('SELECT COUNT(*) FROM ' . DB::table('albums')),
            'orders'  => (int) DB::value('SELECT COUNT(*) FROM ' . DB::table('orders')),
            'paid'    => (int) DB::value('SELECT COUNT(*) FROM ' . DB::table('orders') . ' WHERE status = :s', ['s' => \App\Models\Order::STATUS_PAID]),
            'storage' => (int) DB::value('SELECT COALESCE(SUM(size),0) FROM ' . DB::table('images') . ' WHERE deleted_at IS NULL'),
        ]);
    }

    public function adminImages(): void
    {
        $user = $this->authenticate();
        $this->requireAdmin($user);
        $res = $this->paged('images', '1 = 1', [], max(1, Request::int('page', 1)), 30);
        $res['data'] = array_map([Links::class, 'apiPayload'], $res['data']);
        $this->api(true, '', $res);
    }

    public function adminUsers(): void
    {
        $user = $this->authenticate();
        $this->requireAdmin($user);
        $res = $this->paged('users', '1 = 1', [], max(1, Request::int('page', 1)), 30, 'id, name, email, group_id, is_admin, status, created_at, last_login_at');
        $this->api(true, '', $res);
    }

    public function adminOrders(): void
    {
        $user = $this->authenticate();
        $this->requireAdmin($user);
        $res = $this->paged('orders', '1 = 1', [], max(1, Request::int('page', 1)), 30);
        $this->api(true, '', $res);
    }
}
