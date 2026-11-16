<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$pageTitle = $pageTitle ?? '控制台';
$cp = $currentPath ?? '/admin';
$siteName = (string) ($siteSettings['site_name'] ?? '蜗牛图床');
// 后台名称可自定义；留空则沿用「站点名 后台」
$adminTitle = trim((string) ($siteSettings['admin_title'] ?? ''));
$brandName = $adminTitle !== '' ? $adminTitle : $siteName . ' 后台';
// 新版本提示：只读 GitHub 版本号并缓存，查不到或已是最新就静默。
// 只在后台布局算一次，前台页面不做这个检查、也不会显示提示。
$updateInfo = \App\Services\UpdateChecker::check();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} · 后台 · {{ $siteName }}</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    @if ((string) setting('lightbox_enabled', '1') === '1')
        <link rel="stylesheet" href="{{ asset('lightbox.css') }}">
    @endif
</head>
<body>
@include('partials.icons')

<div class="shell">
    <script>
    /* 先锁上收起态再渲染侧栏，刷新时不会先铺开再缩回 */
    (function () {
        try {
            if (localStorage.getItem('woniu_sidebar_collapsed') === 'collapsed'
                && window.matchMedia('(min-width: 861px)').matches) {
                document.currentScript.parentNode.classList.add('sidebar-collapsed');
            }
        } catch (err) {}
    })();
    </script>
    <aside class="sidebar">
        <a class="brand" href="{{ url('dashboard') }}" title="回到前台">
            <span class="brand-mark" style="background:linear-gradient(135deg,#111827,#374151)">A</span>
            <span class="brand-name">{{ $brandName }}</span>
        </a>

        <div class="nav-label">概览</div>
        <a class="nav-item {!! $cp === '/admin' ? 'active' : '' !!}" href="{{ url('admin') }}">
            <svg class="ic"><use href="#i-dashboard"/></svg><span>控制面板</span>
        </a>

        <div class="nav-label">内容</div>
        <a class="nav-item {!! str_starts_with($cp, '/admin/images') ? 'active' : '' !!}" href="{{ url('admin/images') }}">
            <svg class="ic"><use href="#i-image"/></svg><span>图片管理</span>
        </a>
        <a class="nav-item {!! str_starts_with($cp, '/admin/content/pages') ? 'active' : '' !!}" href="{{ url('admin/content/pages') }}">
            <svg class="ic"><use href="#i-folder"/></svg><span>独立页面</span>
        </a>
        <a class="nav-item {!! str_starts_with($cp, '/admin/content/notices') ? 'active' : '' !!}" href="{{ url('admin/content/notices') }}">
            <svg class="ic"><use href="#i-info"/></svg><span>站内公告</span>
        </a>

        <div class="nav-label">运营</div>
        <a class="nav-item {!! str_starts_with($cp, '/admin/shop/packages') ? 'active' : '' !!}" href="{{ url('admin/shop/packages') }}">
            <svg class="ic"><use href="#i-package"/></svg><span>套餐管理</span>
        </a>
        <a class="nav-item {!! str_starts_with($cp, '/admin/shop/orders') ? 'active' : '' !!}" href="{{ url('admin/shop/orders') }}">
            <svg class="ic"><use href="#i-cart"/></svg><span>订单管理</span>
        </a>
        <a class="nav-item {!! str_starts_with($cp, '/admin/shop/coupons') ? 'active' : '' !!}" href="{{ url('admin/shop/coupons') }}">
            <svg class="ic"><use href="#i-gift"/></svg><span>优惠管理</span>
        </a>
        <a class="nav-item {!! str_starts_with($cp, '/admin/drivers') ? 'active' : '' !!}" href="{{ url('admin/drivers') }}">
            <svg class="ic"><use href="#i-cloud"/></svg><span>驱动管理</span>
        </a>

        <?php
        $pendingTickets = 0;
        $pendingReports = 0;
        try {
            $pendingTickets = \App\Models\Ticket::countOpen();
            $pendingReports = \App\Models\Report::countPending();
        } catch (\Throwable $e) {
        }
        ?>
        <div class="nav-label">支持</div>
        <a class="nav-item {!! str_starts_with($cp, '/admin/support/tickets') ? 'active' : '' !!}" href="{{ url('admin/support/tickets') }}">
            <svg class="ic"><use href="#i-ticket"/></svg><span>工单管理</span>
            @if ($pendingTickets > 0)<span class="badge-count">{!! $pendingTickets !!}</span>@endif
        </a>
        <a class="nav-item {!! str_starts_with($cp, '/admin/support/reports') ? 'active' : '' !!}" href="{{ url('admin/support/reports') }}">
            <svg class="ic"><use href="#i-flag"/></svg><span>举报管理</span>
            @if ($pendingReports > 0)<span class="badge-count">{!! $pendingReports !!}</span>@endif
        </a>
        <a class="nav-item {!! str_starts_with($cp, '/admin/support/feedbacks') ? 'active' : '' !!}" href="{{ url('admin/support/feedbacks') }}">
            <svg class="ic"><use href="#i-message"/></svg><span>意见反馈</span>
        </a>

        <div class="nav-label">配置</div>
        <a class="nav-item {!! str_starts_with($cp, '/admin/users') ? 'active' : '' !!}" href="{{ url('admin/users') }}">
            <svg class="ic"><use href="#i-users"/></svg><span>用户管理</span>
        </a>
        <a class="nav-item {!! str_starts_with($cp, '/admin/groups') ? 'active' : '' !!}" href="{{ url('admin/groups') }}">
            <svg class="ic"><use href="#i-group"/></svg><span>用户分组</span>
        </a>
        <a class="nav-item {!! str_starts_with($cp, '/admin/storages') ? 'active' : '' !!}" href="{{ url('admin/storages') }}">
            <svg class="ic"><use href="#i-storage"/></svg><span>存储策略</span>
        </a>
        <a class="nav-item {!! str_starts_with($cp, '/admin/settings') ? 'active' : '' !!}" href="{{ url('admin/settings') }}">
            <svg class="ic"><use href="#i-sliders"/></svg><span>系统设置</span>
        </a>
        <a class="nav-item {!! str_starts_with($cp, '/admin/logs') ? 'active' : '' !!}" href="{{ url('admin/logs') }}">
            <svg class="ic"><use href="#i-clock"/></svg><span>操作日志</span>
        </a>
    </aside>

    <div class="main">
        <header class="topbar">
            <button class="btn btn-ghost btn-sm menu-btn" type="button" data-sidebar-toggle title="收起 / 展开侧栏" aria-label="收起或展开侧栏" aria-expanded="true">
                <svg class="ic"><use href="#i-panel"/></svg>
            </button>
            <div class="topbar-title">{{ $pageTitle }}</div>
            <!-- 右侧动作组：绝对定位钉在右上角，任何宽度都不会被标题挤走 -->
            <div class="topbar-actions">
            <a class="btn btn-sm" href="{{ url('dashboard') }}" title="回到前台">
                <svg class="ic" style="width:15px;height:15px"><use href="#i-compass"/></svg> <span class="btn-text">前台</span>
            </a>
            <button class="btn btn-ghost btn-sm" type="button" data-theme-toggle title="切换主题">
                <svg class="ic"><use href="#i-moon"/></svg>
            </button>

            <div class="topbar-user">
                <button class="user-menu-btn" type="button" data-user-menu aria-haspopup="true" aria-expanded="false" title="{{ (string) ($me['name'] ?? '') }}">
                    <span class="avatar">{{ mb_substr((string) ($me['name'] ?? 'A'), 0, 1) }}</span>
                    <span class="user-menu-name">{{ (string) ($me['name'] ?? '') }}</span>
                    <span class="user-menu-caret"></span>
                </button>
                <div class="user-dropdown">
                    <div class="user-dropdown-head">
                        <strong>{{ (string) ($me['name'] ?? '') }}</strong>
                        <span title="{{ account_role($me) }}">{{ account_label($me) }}</span>
                    </div>
                    <a href="{{ url('settings') }}">
                        <svg class="ic"><use href="#i-settings"/></svg> 个人设置
                    </a>
                    <a href="{{ url('images') }}">
                        <svg class="ic"><use href="#i-image"/></svg> 我的图库
                    </a>
                    <hr>
                    <form method="post" action="{{ url('logout') }}">
                        {!! csrf_field() !!}                        <button class="item-danger" type="submit">
                            <svg class="ic"><use href="#i-logout"/></svg> 退出登录
                        </button>
                    </form>
                </div>
            </div>
            </div>
        </header>

        <div class="content">
            @foreach (($flashes ?? []) as $flash)
                <div data-flash="{{ (string) $flash['message'] }}" data-flash-type="{{ (string) $flash['type'] }}"></div>
            @endforeach
            {!! $content !!}        </div>

        <footer class="app-foot">
            @if (!empty($updateInfo['has_update']))
                <a class="fm-update" href="{{ (string) ($updateInfo['url'] ?? '') }}" target="_blank" rel="noopener" title="点开查看 GitHub 新版本">发现新版本 v{{ (string) ($updateInfo['latest'] ?? '') }}</a>
            @endif
            @include('partials.footer_meta')
        </footer>
    </div>
</div>

{!! session_ttl_script() !!}@if ((string) setting('lightbox_enabled', '1') === '1')
    <script src="{{ asset('lightbox.js') }}"></script>
@endif
@include('partials.notice_popup')
<script src="{{ asset('app.js') }}"></script>
</body>
</html>
