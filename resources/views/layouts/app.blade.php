<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$pageTitle = $pageTitle ?? '控制台';
$cp = $currentPath ?? '/';
$isUpload = in_array($cp, ['/dashboard', '/upload'], true);
$siteName = (string) ($siteSettings['site_name'] ?? '蜗牛图床');
$ticketOn = (string) ($siteSettings['ticket_enabled'] ?? '1') === '1';
$shopOn = (string) ($siteSettings['shop_enabled'] ?? '0') === '1';

$usedBytes = 0;
$capacity  = 0;
if ($me !== null) {
    $usedBytes = \App\Models\User::usedBytes((int) $me['id']);
    $capacity  = \App\Models\User::capacity($me);
}
$percent = $capacity > 0 ? min(100, round($usedBytes / $capacity * 100, 1)) : 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- 控制台是登录后页面，不该被搜索引擎收录 --}}
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $pageTitle }} · {{ $siteName }}</title>
    @if (!empty($siteSettings['site_favicon']))
        <link rel="icon" href="{{ $siteSettings['site_favicon'] }}">
    @endif
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
        <a class="brand" href="{{ url('dashboard') }}">
            <span class="brand-mark">W</span>
            <span class="brand-name">{{ $siteName }}</span>
        </a>

        <div class="nav-label">图床</div>
        <a class="nav-item {!! $isUpload ? 'active' : '' !!}" href="{{ url('upload') }}">
            <svg class="ic"><use href="#i-upload"/></svg><span>上传图片</span>
        </a>
        <a class="nav-item {!! nav_active('/images') !!}" href="{{ url('images') }}">
            <svg class="ic"><use href="#i-image"/></svg><span>图片管理</span>
        </a>
        <a class="nav-item {!! nav_active('/albums') !!}" href="{{ url('albums') }}">
            <svg class="ic"><use href="#i-album"/></svg><span>我的相册</span>
        </a>
        <a class="nav-item {!! nav_active('/explore') !!}" href="{{ url('explore') }}">
            <svg class="ic"><use href="#i-compass"/></svg><span>发现广场</span>
        </a>
        <a class="nav-item {!! nav_active('/settings') !!}" href="{{ url('settings') }}">
            <svg class="ic"><use href="#i-settings"/></svg><span>个人设置</span>
        </a>
        @if ($ticketOn)
        <a class="nav-item {!! nav_active('/support') !!}" href="{{ url('support/tickets') }}">
            <svg class="ic"><use href="#i-ticket"/></svg><span>我的工单</span>
        </a>
        @endif
        @if ($shopOn)
        <a class="nav-item {!! nav_active('/shop', true) !!}" href="{{ url('shop') }}">
            <svg class="ic"><use href="#i-cart"/></svg><span>套餐商城</span>
        </a>
        <a class="nav-item {!! nav_active('/shop/orders') !!}" href="{{ url('shop/orders') }}">
            <svg class="ic"><use href="#i-card"/></svg><span>我的订单</span>
        </a>
        @endif

        <div class="sidebar-foot">
            @if ($capacity > 0)
                <div class="quota">
                    <div class="quota-head">
                        <span>存储空间</span>
                        <span>{{ format_bytes($usedBytes) }} / {{ format_bytes($capacity) }}</span>
                    </div>
                    <div class="quota-bar"><span style="width: {{ (string) $percent }}%"></span></div>
                </div>
            @else
                <div class="quota">
                    <div class="quota-head"><span>存储空间</span><span>不限</span></div>
                    <div class="quota-bar"><span style="width: 6%"></span></div>
                </div>
            @endif
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <button class="btn btn-ghost btn-sm menu-btn" type="button" data-sidebar-toggle title="收起 / 展开侧栏" aria-label="收起或展开侧栏" aria-expanded="true">
                <svg class="ic"><use href="#i-panel"/></svg>
            </button>
            <div class="topbar-title">{{ $pageTitle }}</div>
            <!-- 右侧动作组：绝对定位钉在右上角，任何宽度都不会被标题挤走 -->
            <div class="topbar-actions">
            <a class="btn btn-sm" href="{{ url('images') }}">
                <svg class="ic" style="width:15px;height:15px"><use href="#i-image"/></svg> <span class="btn-text">我的图库</span>
            </a>
            @if ($ticketOn)
            <a class="btn btn-sm" href="{{ url('support/tickets/new') }}">
                <svg class="ic" style="width:15px;height:15px"><use href="#i-ticket"/></svg> <span class="btn-text">创建工单</span>
            </a>
            @endif
            @if ($shopOn)
            <a class="btn btn-sm btn-primary" href="{{ url('shop') }}">
                <svg class="ic" style="width:15px;height:15px"><use href="#i-cart"/></svg> <span class="btn-text">购买套餐</span>
            </a>
            @endif
            @if (is_admin())
                <a class="btn btn-sm btn-primary" href="{{ url('admin') }}">
                    <svg class="ic" style="width:15px;height:15px"><use href="#i-dashboard"/></svg> <span class="btn-text">后台管理</span>
                </a>
            @endif
            <button class="btn btn-ghost btn-sm" type="button" data-theme-toggle title="切换主题">
                <svg class="ic"><use href="#i-moon"/></svg>
            </button>

            <div class="topbar-user">
                <button class="user-menu-btn" type="button" data-user-menu aria-haspopup="true" aria-expanded="false" title="{{ (string) ($me['name'] ?? '') }}">
                    <span class="avatar">{{ mb_substr((string) ($me['name'] ?? 'U'), 0, 1) }}</span>
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
                    @if ($ticketOn)
                    <a href="{{ url('support/tickets') }}">
                        <svg class="ic"><use href="#i-ticket"/></svg> 我的工单
                    </a>
                    @endif
                    @if ($shopOn)
                    <a href="{{ url('shop/orders') }}">
                        <svg class="ic"><use href="#i-card"/></svg> 我的订单
                    </a>
                    @endif
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
            @include('partials.notices', ['noticeFluid' => true])
            @foreach (($flashes ?? []) as $flash)
                <div data-flash="{{ (string) $flash['message'] }}" data-flash-type="{{ (string) $flash['type'] }}"></div>
            @endforeach
            {!! $content !!}        </div>

        <footer class="app-foot">
            @include('partials.footer_meta', ['footerPages' => []])
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
