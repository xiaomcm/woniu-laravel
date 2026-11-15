<?php
/**
 * 全站统一页脚署名行。
 *
 * 内容固定三段，任何页面都一致：
 *   © 年份 v版本号 站点名 · Powered by WONIU · Powered by 站点名
 *
 * 版本号读 config/defaults.php 的 version（发版时由 tools/release.php 改写），
 * 不在模板里写死，免得改了版本号页脚还显示旧的。
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

// 产品版本号：跟 config/defaults.php 的 version 同一个源，发版脚本会同步改
$__fmVer = trim((string) \App\Core\Config::get('app.version', ''));
?>
<span>&copy; {!! date('Y') !!} {{ $__fmSite }}</span>
@if ($__fmVer !== '')
    <span class="dot">·</span><span class="ver">v{{ $__fmVer }}</span>
@endif
@foreach ($__fmPages as $__fmPage)
    <span class="dot">·</span><a href="{{ url('p/' . (string) $__fmPage['slug']) }}" style="color:inherit">{{ (string) $__fmPage['title'] }}</a>
@endforeach
<span class="dot">·</span><span>{{ $__fmBrand }}</span>
@if (!empty($siteSettings['site_icp']))
    <span class="dot">·</span><span>{{ (string) $siteSettings['site_icp'] }}</span>
@endif
<span class="dot">·</span><span>Powered by {{ $__fmSite }}</span>
