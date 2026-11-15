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
use App\Core\Setting;
use App\Models\Album;
use App\Models\Group;
use App\Models\Image;
use App\Models\Page;
use App\Models\Storage as StorageModel;
use App\Models\User;
use App\Services\StatsService;

class HomeController extends Controller
{
    /**
     * 站点主页（营销落地页）—— 游客专属。
     *
     * 登录用户访问 / 一律 302 弹到 /dashboard：主页是「还没进门的人看的门面」，
     * 登录后还停在门面等于没进门。URL 层面彻底隔离，不靠前端 JS 判断。
     */
    public function index(): HttpResponse
    {
        if (Auth::check()) {
            \App\Core\Response::redirect('/dashboard');
        }

        // 落地页数据：统计 + 最新公开图作展示墙
        $showcase = [];
        if (Setting::bool('explore_enabled', true)) {
            $page     = Image::explore([], 1, 8);
            $showcase = $page['items'] ?? [];
        }

        return $this->view('landing', [
            'pageTitle'     => '首页',
            'me'            => null,
            'siteSettings'  => Setting::all(),
            'showcase'      => $showcase,
            'stats'         => [
                'images'    => Image::countAll(),
                'public'    => Image::countAll(),
                'users'     => User::countAll(),
                'storages'  => count(StorageModel::enabled()),
            ],
            'exploreOn'     => Setting::bool('explore_enabled', true),
            'registerOn'    => Setting::bool('register_enabled', true),
            'shopOn'        => Setting::bool('shop_enabled', false),
            'footerPages'   => Page::footerPages(),
        ], 'layouts/landing');
    }

    /**
     * 用户控制台 —— 登录专属。
     *
     * 以前控制台和落地页共用 / 一个 URL，靠登录态在同一个地址里切换视图，
     * 结果登录后「打开主页」依然成立。现在拆成两个独立地址：
     *   /          → 落地页（游客）
     *   /dashboard → 控制台（登录）
     * 未登录访问 /dashboard 会走 requireLogin() 跳登录页并带 redirect 回跳。
     */
    public function dashboard(): HttpResponse
    {
        $user = Auth::requireLogin();
        $uid  = (int) $user['id'];

        return $this->view('home', [
            'pageTitle' => '控制台',
            'storage'   => StorageModel::defaultStorage(),
            'group'     => Group::find((int) $user['group_id']),
            'stats'     => StatsService::userOverview($uid),
            'series'    => Image::dailySeries(7, $uid),
            'recent'    => Image::recentForUser($uid, 8),
        ], 'layouts/app');
    }
}
