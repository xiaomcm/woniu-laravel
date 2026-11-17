<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response as HttpResponse;
use App\Core\Auth;
use App\Core\Database as DB;
use App\Core\Request;
use App\Core\Setting;
use App\Models\Group;
use App\Models\Image;

class ExploreController extends Controller
{
    public function index(): HttpResponse
    {
        if (!Setting::bool('explore_enabled', true)) {
            \App\Core\Response::featureDisabled('图片广场', '站主暂时关闭了图片广场，你可以先看看其他功能。');
        }

        $page = max(1, Request::int('page', 1));
        $perPage = max(6, min(60, Setting::int('page_size', 24)));
        $filters = [
            'keyword' => (string) Request::input('q', ''),
            'sort'    => (string) Request::input('sort', 'newest'),
            'tag'     => (string) Request::input('tag', ''),
        ];

        if (!in_array($filters['sort'], ['newest', 'hot', 'oldest'], true)) {
            $filters['sort'] = 'newest';
        }

        $result = Image::explore($filters, $page, $perPage);

        return $this->view('explore', [
            'pageTitle' => '发现',
            'images'    => $result['items'],
            'pager'     => $result,
            'filters'   => $filters,
            'tags'      => Image::tagCloud(18),
            'total'     => $result['total'],
        ], 'layouts/site');
    }

    public function user(string $id): HttpResponse
    {
        if (!Setting::bool('user_home_enabled', true)) {
            \App\Core\Response::featureDisabled('用户主页', '站主暂时关闭了用户主页。');
        }

        $user = DB::fetch(
            'SELECT id, name, avatar, created_at FROM ' . DB::table('users') . ' WHERE id = :id',
            ['id' => (int) $id]
        );
        if ($user === null) {
            abort(404, '用户不存在');
        }

        $page = max(1, Request::int('page', 1));
        $perPage = max(6, min(60, Setting::int('page_size', 24)));
        $result = Image::explore(['user_id' => (int) $user['id'], 'sort' => 'newest'], $page, $perPage);

        return $this->view('user_home', [
            'pageTitle'   => (string) $user['name'] . ' 的主页',
            'owner'       => $user,
            'images'      => $result['items'],
            'pager'       => $result,
            'publicCount' => $result['total'],
        ], 'layouts/site');
    }
}
