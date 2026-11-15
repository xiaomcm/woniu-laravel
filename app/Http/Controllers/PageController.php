<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response as HttpResponse;
use App\Models\Notice;
use App\Models\Page;

class PageController extends Controller
{
    public function show(string $slug): HttpResponse
    {
        $page = Page::findBySlug($slug);
        if ($page === null || (int) $page['status'] !== 1) {
            abort(404, '页面不存在');
        }

        return $this->view('page', [
            'pageTitle' => (string) $page['title'],
            'page'      => $page,
            'siblings'  => Page::footerPages(),
        ], 'layouts/site');
    }

    public function notices(): HttpResponse
    {
        return $this->view('notices', [
            'pageTitle' => '站内公告',
            'notices'   => Notice::active(20),
        ], 'layouts/site');
    }
}
