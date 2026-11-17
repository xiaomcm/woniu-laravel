<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$pageTitle = $pageTitle ?? '';
$siteName = (string) ($siteSettings['site_name'] ?? '蜗牛图床');
$seoDesc = trim((string) ($siteSettings['site_seo_description'] ?? ''));
$seoDesc = $seoDesc !== '' ? $seoDesc : (string) ($siteSettings['site_description'] ?? '');
?>
<!DOCTYPE html>
<html lang="zh-CN" {!! theme_html_attrs() !!}>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if ($seoDesc !== '')
        <meta name="description" content="{{ $seoDesc }}">
    @endif
    <title>{{ $pageTitle }} · {{ $siteName }}</title>
    @if (!empty($siteSettings['site_favicon']))
        <link rel="icon" href="{{ $siteSettings['site_favicon'] }}">
    @endif
    <link rel="stylesheet" href="{{ asset('app.css') }}">
</head>
<body>
@include('partials.icons')
<div class="auth-wrap">
    <div class="auth-art">
        <div class="auth-art-inner">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:38px">
                <span class="brand-mark" style="background:rgba(255,255,255,.22);box-shadow:none">L</span>
                <span style="font-weight:650;font-size:16px">{{ $siteName }}</span>
            </div>
            <h1>把图床<br>做得简单一点</h1>
            <p>{{ (string) ($siteSettings['site_description'] ?? '支持本地与多家云存储、相册管理、API 上传的图床程序。') }}</p>
        </div>
        <div class="auth-features">
            <div class="auth-feature">
                <svg class="ic"><use href="#i-upload"/></svg>
                <span>拖拽、粘贴、URL 拉取，一次可传多张</span>
            </div>
            <div class="auth-feature">
                <svg class="ic"><use href="#i-storage"/></svg>
                <span>本地 / S3 / OSS / COS / 七牛 / 又拍云 / FTP / WebDAV</span>
            </div>
            <div class="auth-feature">
                <svg class="ic"><use href="#i-shield"/></svg>
                <span>相册分享、审核流、API Token 与防盗链</span>
            </div>
        </div>
    </div>

    <div class="auth-frame">
        <div class="auth-card">
            {!! $content !!}
        </div>
        <footer class="auth-foot-brand">
            @include('partials.footer_meta')
        </footer>
    </div>
</div>
@include('partials.notice_popup')
<script src="{{ asset('app.js') }}"></script>
</body>
</html>
