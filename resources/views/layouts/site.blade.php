<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$pageTitle = $pageTitle ?? '发现';
$siteName = (string) ($siteSettings['site_name'] ?? '蜗牛图床');
// SEO：内页标题 = 页名 · 站名；描述优先用 SEO 介绍，留空回落到站点描述
$seoDesc = trim((string) ($siteSettings['site_seo_description'] ?? ''));
$seoDesc = $seoDesc !== '' ? $seoDesc : (string) ($siteSettings['site_description'] ?? '');
$seoKeywords = trim((string) ($siteSettings['site_keywords'] ?? ''));
$logo = (string) ($siteSettings['site_logo'] ?? '');
$shopOn = (string) ($siteSettings['shop_enabled'] ?? '0') === '1';
$ticketOn = (string) ($siteSettings['ticket_enabled'] ?? '1') === '1';

$footerPages = [];
try {
    $footerPages = \App\Models\Page::footerPages();
} catch (\Throwable $e) {
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if ($seoDesc !== '')
        <meta name="description" content="{{ $seoDesc }}">
    @endif
    @if ($seoKeywords !== '')
        <meta name="keywords" content="{{ $seoKeywords }}">
    @endif
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

<div class="site-shell">
    <header class="site-bar">
        <div class="site-bar-left">
            <a class="brand brand-sm" href="{{ url(!empty($me) ? 'dashboard' : '/') }}">
                @if ($logo !== '')
                    <img class="brand-logo" src="{{ $logo }}" alt="{{ $siteName }}">
                @else
                    <span class="brand-mark">W</span>
                @endif
                <span class="brand-name">{{ $siteName }}</span>
            </a>
            <nav class="site-nav">
                <a class="site-link {!! nav_active('/explore') !!}" href="{{ url('explore') }}">发现广场</a>
                <a class="site-link {!! nav_active('/api-docs') !!}" href="{{ url('api-docs') }}">API 文档</a>
                @if ($shopOn)
                    <a class="site-link {!! nav_active('/shop') !!}" href="{{ url('shop') }}">套餐商城</a>
                @endif
                @if (!empty($me))
                    <a class="site-link" href="{{ url('images') }}">我的图库</a>
                    @if ($ticketOn)
                        <a class="site-link {!! nav_active('/support') !!}" href="{{ url('support/tickets') }}">我的工单</a>
                    @endif
                    <a class="site-link" href="{{ url('upload') }}">上传图片</a>
                @endif
            </nav>
        </div>
        <!-- 右侧动作组：绝对定位钉在右上角，任何宽度都不会被中间内容挤走 -->
        <div class="site-nav-actions">
            @if (!empty($me))
                <a class="btn btn-sm btn-primary site-cta" href="{{ url('dashboard') }}">我的控制台</a>
            @else
                <a class="site-link site-keep" href="{{ url('login') }}">账号登录</a>
                @if ((string) ($siteSettings['register_enabled'] ?? '1') === '1')
                    <a class="btn btn-sm btn-primary" href="{{ url('register') }}">免费注册</a>
                @endif
            @endif
            @if (!empty($me) && (int) ($me['is_admin'] ?? 0) === 1)
                <a class="btn btn-sm btn-primary" href="{{ url('admin') }}" title="进入后台管理">
                    <svg class="ic" style="width:15px;height:15px"><use href="#i-dashboard"/></svg> <span class="btn-text">后台管理</span>
                </a>
            @endif
            <button class="btn btn-ghost btn-sm" type="button" data-theme-toggle title="切换主题">
                <svg class="ic"><use href="#i-moon"/></svg>
            </button>

            @if (!empty($me))
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
                        <a href="{{ url('dashboard') }}">
                            <svg class="ic"><use href="#i-upload"/></svg> 我的控制台
                        </a>
                        <a href="{{ url('explore') }}">
                            <svg class="ic"><use href="#i-compass"/></svg> 发现广场
                        </a>
                        <a href="{{ url('images') }}">
                            <svg class="ic"><use href="#i-image"/></svg> 我的图库
                        </a>
                        @if ($shopOn)
                            <a href="{{ url('shop') }}">
                                <svg class="ic"><use href="#i-package"/></svg> 套餐商城
                            </a>
                        @endif
                        <a href="{{ url('settings') }}">
                            <svg class="ic"><use href="#i-settings"/></svg> 个人设置
                        </a>
                        <a href="{{ url('api-docs') }}">
                            <svg class="ic"><use href="#i-storage"/></svg> API 文档
                        </a>
                        <hr>
                        <form method="post" action="{{ url('logout') }}">
                            {!! csrf_field() !!}                            <button class="item-danger" type="submit">
                                <svg class="ic"><use href="#i-logout"/></svg> 退出登录
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </header>

    @include('partials.notices')

    <main class="site-main">
        @foreach (($flashes ?? []) as $flash)
            <div data-flash="{{ (string) $flash['message'] }}" data-flash-type="{{ (string) $flash['type'] }}"></div>
        @endforeach
        {!! $content !!}    </main>

    <footer class="site-foot">
        @include('partials.footer_meta', ['footerPages' => $footerPages])
    </footer>
</div>

{!! session_ttl_script() !!}@if ((string) setting('lightbox_enabled', '1') === '1')
    <script src="{{ asset('lightbox.js') }}"></script>
@endif
@include('partials.notice_popup')
<script src="{{ asset('app.js') }}"></script>
</body>
</html>
