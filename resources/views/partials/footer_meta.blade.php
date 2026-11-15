<?php
/**
 * 全站统一页脚署名行。
 *
 * 内容固定三段，任何页面都一致：
 *   © 年份 站点名 · Powered by WONIU · Powered by 站点名 2.0
 *
 * 中间那段读后台「系统设置 → 页脚文字」（site_footer），为空时回落到默认的
 * Powered by WONIU，保证这条信息在任何配置下都不会消失。
 * 独立页面链接（footerPages）与备案号（site_icp）作为附加项插在中间。
 *
 * 用法：
 *   @include('partials.footer_meta')                          不带独立页面
 *   @include('partials.footer_meta', ['footerPages' => $footerPages])  带独立页面
 */
$__fmSite  = (string) ($siteSettings['site_name'] ?? '蜗牛图床');
$__fmBrand = trim((string) ($siteSettings['site_footer'] ?? ''));
if ($__fmBrand === '') {
    $__fmBrand = 'Powered by WONIU';
}
$__fmPages = $footerPages ?? [];
/* 产品版本号：这一份代码是 2.0 分支，固定 2.0。
   注意别用 config('app.version') —— 那个值来自 config/defaults.php 的资源版本号，
   是给静态资源做缓存版本拼接用的，不是产品版本号。 */
$__fmVer = '2.0';
?>
<span>&copy; {!! date('Y') !!} {{ $__fmSite }}</span>
@foreach ($__fmPages as $__fmPage)
    <span class="dot">·</span><a href="{{ url('p/' . (string) $__fmPage['slug']) }}" style="color:inherit">{{ (string) $__fmPage['title'] }}</a>
@endforeach
<span class="dot">·</span><span>{{ $__fmBrand }}</span>
@if (!empty($siteSettings['site_icp']))
    <span class="dot">·</span><span>{{ (string) $siteSettings['site_icp'] }}</span>
@endif
<span class="dot">·</span><span>Powered by {{ $__fmSite }} {{ $__fmVer }}</span>
