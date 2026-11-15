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
use App\Models\Notice;
use App\Models\Page;

class ContentController extends Controller
{
    public function pages(): HttpResponse
    {
        Auth::requireAdmin();

        return $this->view('admin/pages', [
            'pageTitle' => '独立页面',
            'pages'     => Page::allRows(),
        ], 'layouts/admin');
    }

    public function pageStore(): void
    {
        Auth::requireAdmin();
        $this->validateCsrf();

        $data = $this->pagePayload();
        if ($data['title'] === '') {
            flash('error', '页面标题不能为空');
            back();
        }
        if ($data['slug'] === '') {
            $data['slug'] = 'page-' . str_random(6);
        }
        if (Page::slugExists($data['slug'])) {
            flash('error', '该访问路径已被占用');
            back();
        }

        Page::create($data);
        flash('success', '页面已创建');
        redirect('admin/content/pages');
    }

    public function pageUpdate(string $id): void
    {
        $id = (int) $id;
        Auth::requireAdmin();
        $this->validateCsrf();

        if (Page::find($id) === null) {
            abort(404, '页面不存在');
        }

        $data = $this->pagePayload();
        if ($data['title'] === '') {
            flash('error', '页面标题不能为空');
            back();
        }
        if ($data['slug'] !== '' && Page::slugExists($data['slug'], $id)) {
            flash('error', '该访问路径已被占用');
            back();
        }

        Page::updateById($id, $data);
        flash('success', '页面已保存');
        redirect('admin/content/pages');
    }

    public function pageDelete(string $id): void
    {
        $id = (int) $id;
        Auth::requireAdmin();
        $this->validateCsrf();

        Page::deleteById($id);
        flash('success', '页面已删除');
        redirect('admin/content/pages');
    }

    public function notices(): HttpResponse
    {
        Auth::requireAdmin();

        return $this->view('admin/notices', [
            'pageTitle' => '站内公告',
            'notices'   => Notice::allRows(),
            'levels'    => Notice::levels(),
        ], 'layouts/admin');
    }

    public function noticeStore(): void
    {
        Auth::requireAdmin();
        $this->validateCsrf();

        $data = $this->noticePayload();
        if ($data['title'] === '') {
            flash('error', '公告标题不能为空');
            back();
        }

        Notice::create($data);
        flash('success', '公告已发布');
        redirect('admin/content/notices');
    }

    public function noticeUpdate(string $id): void
    {
        $id = (int) $id;
        Auth::requireAdmin();
        $this->validateCsrf();

        if (Notice::find($id) === null) {
            abort(404, '公告不存在');
        }

        $data = $this->noticePayload();
        if ($data['title'] === '') {
            flash('error', '公告标题不能为空');
            back();
        }

        Notice::updateById($id, $data);
        flash('success', '公告已保存');
        redirect('admin/content/notices');
    }

    public function noticeDelete(string $id): void
    {
        $id = (int) $id;
        Auth::requireAdmin();
        $this->validateCsrf();

        Notice::deleteById($id);
        flash('success', '公告已删除');
        redirect('admin/content/notices');
    }

    private function pagePayload(): array
    {
        $statusPresent = Request::bool('status_present');
        return [
            'title'          => mb_substr(trim((string) Request::input('title', '')), 0, 120),
            'slug'           => strtolower(trim((string) Request::input('slug', ''))),
            'content'        => (string) Request::input('content', ''),
            'status'         => $statusPresent ? (Request::bool('status') ? 1 : 0) : 1,
            'sortable'       => Request::int('sortable', 0),
            'show_in_footer' => Request::bool('show_in_footer') ? 1 : 0,
        ];
    }

    private function noticePayload(): array
    {
        $expire = trim((string) Request::input('expire_at', ''));
        $expireTime = $expire === '' ? null : strtotime($expire);

        $statusPresent = Request::bool('status_present');
        return [
            'title'     => mb_substr(trim((string) Request::input('title', '')), 0, 120),
            'content'   => (string) Request::input('content', ''),
            'level'     => (string) Request::input('level', 'info'),
            'pinned'    => Request::bool('pinned') ? 1 : 0,
            'status'    => $statusPresent ? (Request::bool('status') ? 1 : 0) : 1,
            // 全站弹窗：勾选后全站所有页面都会弹一次，用户关闭后不再打扰
            'popup'     => Request::bool('popup') ? 1 : 0,
            'expire_at' => $expireTime === false || $expireTime === null ? null : date('Y-m-d H:i:s', $expireTime),
        ];
    }
}
