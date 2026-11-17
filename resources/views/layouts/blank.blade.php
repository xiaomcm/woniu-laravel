<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$pageTitle = $pageTitle ?? '';
$siteName = (string) ($siteSettings['site_name'] ?? '蜗牛图床');
?>
<!DOCTYPE html>
<html lang="zh-CN" {!! theme_html_attrs() !!}>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} · {{ $siteName }}</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    <link rel="stylesheet" href="{{ asset('lightbox.css') }}">
</head>
<body>
@include('partials.icons')
<header class="blank-head">
    <a class="brand" href="{{ url('/') }}" style="padding:0">
        <span class="brand-mark">W</span>
        <span class="brand-name">{{ $siteName }}</span>
    </a>
    <div style="flex:1"></div>
    <button class="btn btn-ghost btn-sm" type="button" data-theme-toggle title="切换主题">
        <svg class="ic"><use href="#i-moon"/></svg>
    </button>
</header>

<div class="blank-body">
    {!! $content !!}</div>

<footer class="blank-foot">
    @include('partials.footer_meta')
</footer>
@include('partials.notice_popup')
<script src="{{ asset('app.js') }}"></script>
<script src="{{ asset('lightbox.js') }}"></script>
</body>
</html>
