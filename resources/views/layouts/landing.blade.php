<?php
/**
 * 蜗牛图床 WONIU — 科技风落地页布局
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$pageTitle = $pageTitle ?? '首页';
$siteName = (string) ($siteSettings['site_name'] ?? '蜗牛图床');
$siteDesc = (string) ($siteSettings['site_description'] ?? '极速、稳定、优雅的图片托管服务');
// SEO：副标题拼进标题；SEO 介绍优先，留空回落到站点描述；关键词直接进 meta
$siteSubtitle = trim((string) ($siteSettings['site_subtitle'] ?? ''));
$seoDesc = trim((string) ($siteSettings['site_seo_description'] ?? ''));
$seoDesc = $seoDesc !== '' ? $seoDesc : $siteDesc;
$seoKeywords = trim((string) ($siteSettings['site_keywords'] ?? ''));
// 标题：首页用「站名 · 副标题」，内页用「页名 · 站名」，再没副标题就只剩站名
$fullTitle = $siteSubtitle !== ''
    ? ($pageTitle === '首页' ? $siteName . ' · ' . $siteSubtitle : $pageTitle . ' · ' . $siteName)
    : ($pageTitle === '首页' ? $siteName : $pageTitle . ' · ' . $siteName);
$logo = (string) ($siteSettings['site_logo'] ?? '');
$shopOn = (string) ($siteSettings['shop_enabled'] ?? '0') === '1';
$exploreOn = (string) ($siteSettings['explore_enabled'] ?? '1') === '1';
$registerOn = (string) ($siteSettings['register_enabled'] ?? '1') === '1';

$footerPages = $footerPages ?? [];
if ($footerPages === []) {
    try {
        $footerPages = \App\Models\Page::footerPages();
    } catch (\Throwable $e) {
        $footerPages = [];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $seoDesc }}">
    @if ($seoKeywords !== '')
        <meta name="keywords" content="{{ $seoKeywords }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $seoDesc }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    @if (!empty($siteSettings['site_url']))
        <meta property="og:url" content="{{ $siteSettings['site_url'] }}">
    @endif
    <meta name="theme-color" content="#05080f">
    <title>{{ $fullTitle }}</title>
    @if (!empty($siteSettings['site_favicon']))
        <link rel="icon" href="{{ $siteSettings['site_favicon'] }}">
    @endif
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    <link rel="stylesheet" href="{{ asset('landing.css') }}">
    @if ((string) setting('lightbox_enabled', '1') === '1')
        <link rel="stylesheet" href="{{ asset('lightbox.css') }}">
    @endif
    <script>
        /* 落地页以深空科技风为基准；用户手动切换过主题后仍尊重其选择 */
        (function () {
            try {
                if (localStorage.getItem('woniu_theme') === null) {
                    localStorage.setItem('woniu_theme', 'dark');
                }
            } catch (e) { /* 隐私模式下 localStorage 不可用，交给 CSS 默认深色 */ }
        })();
    </script>
</head>
<body>
@include('partials.icons')

<!-- 深空背景：渐变光晕 + 动态网格 + 极光球 + 粒子网络 -->
<div class="lx-bg" aria-hidden="true"></div>
<div class="lx-grid" aria-hidden="true"></div>
<div class="lx-orb a" aria-hidden="true"></div>
<div class="lx-orb b" aria-hidden="true"></div>
<div class="lx-orb c" aria-hidden="true"></div>
<canvas id="lx-canvas" aria-hidden="true"></canvas>

<div class="lx-wrap">

<header class="lx-nav">
    <div class="lx-nav-left">
        <a class="lx-brand" href="{{ url('/') }}">
            @if ($logo !== '')
                <img class="lx-logo" src="{{ $logo }}" alt="{{ $siteName }}">
            @else
                <span class="lx-mark">W</span>
            @endif
            <b>{{ $siteName }}</b>
        </a>
        <nav class="lx-nav-links">
            <a class="lx-link" href="{{ url('/') }}">首页</a>
            @if ($exploreOn)
                <a class="lx-link" href="{{ url('explore') }}">发现广场</a>
            @endif
            @if ($shopOn)
                <a class="lx-link" href="{{ url('shop') }}">套餐商城</a>
            @endif
            <a class="lx-link" href="{{ url('/') }}#api">开放 API</a>
            @if (!empty($me) && (int) ($me['is_admin'] ?? 0) === 1)
                <a class="lx-link" href="{{ url('admin') }}">后台管理</a>
            @endif
        </nav>
    </div>
    <!-- 右侧动作组：绝对定位钉在右上角，任何宽度都不会被中间内容挤走 -->
    <div class="lx-nav-actions">
        @if (!empty($me))
            <a class="lx-btn lx-btn-ghost lx-btn-sm site-keep" href="{{ url('upload') }}">上传图片</a>
            <a class="lx-btn lx-btn-primary lx-btn-sm" href="{{ url('dashboard') }}">进入控制台</a>
        @else
            <a class="lx-link site-keep" href="{{ url('login') }}">登录</a>
            @if ($registerOn)
                <a class="lx-btn lx-btn-primary lx-btn-sm" href="{{ url('register') }}">免费注册</a>
            @endif
        @endif
        <button class="lx-icon-btn" type="button" data-theme-toggle title="切换主题" aria-label="切换主题">
            <svg class="ic"><use href="#i-moon"/></svg>
        </button>
    </div>
</header>

<main>
{!! $content !!}
</main>

<footer class="lx-foot">
    <div class="lx-foot-inner">
        <div class="lx-foot-brand">
            <span class="lx-mark" style="width:34px;height:34px;font-size:15px">W</span>
            <div>
                <strong>{{ $siteName }}</strong>
                <p>{{ $siteDesc }}</p>
            </div>
        </div>
        <div class="lx-foot-links">
            <a href="{{ url('/') }}">首页</a>
            @if ($exploreOn)
                <a href="{{ url('explore') }}">发现广场</a>
            @endif
            @if ($shopOn)
                <a href="{{ url('shop') }}">套餐商城</a>
            @endif
            <a href="{{ url('/') }}#api">开放 API</a>
            @foreach ($footerPages as $fp)
                <a href="{{ url('p/' . (string) $fp['slug']) }}">{{ (string) $fp['title'] }}</a>
            @endforeach
            @if (!empty($me) && (string) ($siteSettings['ticket_enabled'] ?? '1') === '1')
                <a href="{{ url('support/tickets') }}">我的工单</a>
            @endif
        </div>
    </div>
    <div class="lx-foot-meta">
        @include('partials.footer_meta', ['footerPages' => []])
    </div>
</footer>

</div><!-- /.lx-wrap -->

{!! session_ttl_script() !!}
@if ((string) setting('lightbox_enabled', '1') === '1')
    <script src="{{ asset('lightbox.js') }}"></script>
@endif
@include('partials.notice_popup')
<script src="{{ asset('app.js') }}"></script>
<script src="{{ asset('landing.js') }}"></script>
</body>
</html>
