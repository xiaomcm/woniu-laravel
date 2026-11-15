<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Response as HttpResponse;
use App\Http\Controllers\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Album;
use App\Models\Image;
use App\Models\Storage as StorageModel;
use App\Services\Blank;
use App\Storage\Manager;

class ImageController extends Controller
{
    public function index(): HttpResponse
    {
        Auth::requireAdmin();
        $page = max(1, Request::int('page', 1));
        $filters = [
            'keyword' => (string) Request::input('keyword', ''),
            'status'  => Request::input('status', ''),
            'trashed' => Request::bool('trashed', false),
        ];
        $result = Image::paginate($filters, $page, 30);

        return $this->view('admin/images', [
            'pageTitle' => '图片管理',
            'images'    => $result['items'],
            'pager'     => $result,
            'filters'   => $filters,
        ], 'layouts/admin');
    }

    public function preview(string $id): HttpResponse
    {
        Auth::requireAdmin();
        $image = Image::find((int) $id);
        if ($image === null) {
            abort(404, '图片不存在');
        }

        $sha1 = (string) $image['sha1'];
        $thumb = BASE_PATH . '/storage/thumbs/' . substr($sha1, 0, 2) . '/' . $sha1 . '.jpg';
        $raw = BASE_PATH . '/storage/uploads/' . ltrim((string) $image['pathname'], '/');
        $rawRequested = Request::bool('raw', false);
        $previewUrl = url('admin/images/' . (int) $image['id'] . '/preview');

        // 浏览器地址栏/链接直接导航到 ?raw=1 时，给一个带关闭按钮的查看器页面。
        // <img>、灯箱、?file=1 请求要的是二进制原图，绝不能套 HTML，否则变成破图。
        // 判定用 Accept 头：导航请求带 text/html，图片请求带 image/*。
        $accept = strtolower((string) Request::header('Accept'));
        if ($rawRequested && !Request::bool('file', false) && str_contains($accept, 'text/html')) {
            $remoteUrl = ((string) $image['url'] !== '' && ($image['deleted_at'] ?? null) === null)
                ? (string) $image['url']
                : '';
            // 本地有副本就走同一入口的 file=1，仍受管理员鉴权保护；远程存储直接用原始地址
            $source = (is_file($raw) || $remoteUrl === '')
                ? $previewUrl . '?raw=1&file=1'
                : $remoteUrl;

            return $this->view('admin/image_viewer', [
                'pageTitle' => '图片预览',
                'image'     => $image,
                'source'    => $source,
                'thumbUrl'  => $previewUrl,
                'backUrl'   => url('admin/images'),
            ], null);
        }

        // 后台审核入口：默认给缩略图，?raw=1 才给原图，避免任何公开地址绕过下线状态
        if (!$rawRequested && is_file($thumb)) {
            Response::file($thumb);
        }
        if (is_file($raw)) {
            Response::file($raw);
        }

        // 远程储存的图片本地没有副本，只有可访问的原始地址
        // 回收站里的图可能连同文件一起没了，跳过去只会得到 404，不如直接给白块
        if ((string) $image['url'] !== '' && ($image['deleted_at'] ?? null) === null) {
            redirect((string) $image['url']);
        }
        // 文件已经没了（硬删过）也不能让审核列表出现破图，给同比例的白块
        $blank = Blank::path((int) $image['width'], (int) $image['height']);
        if ($blank !== null) {
            Response::file($blank, null, 300, true);
        }
        abort(404);
    }

    public function audit(): void
    {
        $this->validateCsrf();
        Auth::requireAdmin();
        $status = Request::int('audit_status', 1);
        if (!in_array($status, [0, 1, 2], true)) {
            $this->api(false, '状态不合法');
        }
        $ids = $this->ids();
        if ($ids === []) {
            $this->api(false, '请选择图片');
        }
        foreach ($ids as $id) {
            // 通过时清掉下线备注，避免恢复后的图片还挂着「举报下线」的说明
            $data = ['status' => $status, 'updated_at' => now()];
            if ($status === 1) {
                $data['review_note'] = null;
            }
            \App\Core\Database::update('images', $data, 'id = :id', ['id' => $id]);
        }
        $label = [0 => '待审核', 1 => '已通过', 2 => '已拒绝'][$status];
        $this->api(true, '已将 ' . count($ids) . ' 张图片标记为' . $label);
    }

    public function delete(): void
    {
        $this->validateCsrf();
        Auth::requireAdmin();
        $ids = $this->ids();
        $force = Request::bool('force', false);
        $count = 0;
        foreach ($ids as $id) {
            $image = Image::find($id);
            if ($image === null) {
                continue;
            }
            if ($force) {
                $storage = StorageModel::find((int) $image['storage_id']);
                if ($storage !== null) {
                    Manager::make($storage)->delete((string) $image['pathname']);
                }
                $thumb = BASE_PATH . '/storage/thumbs/' . substr((string) $image['sha1'], 0, 2) . '/' . $image['sha1'] . '.jpg';
                if (is_file($thumb)) {
                    @unlink($thumb);
                }
                Image::forceDeleteById($id);
            } else {
                Image::softDelete($id);
            }
            Album::recount((int) $image['album_id']);
            $count++;
        }
        $this->api(true, ($force ? '已彻底删除 ' : '已移入回收站 ') . $count . ' 张');
    }
}
