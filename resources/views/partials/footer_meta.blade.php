<?php
/**
 * 全站统一页脚署名行。
 *
 * 只显示后台配置里真的填了 / 勾了的内容，没配的一段都不出现：
 *   - site_footer：页脚文字 / 版权授权
 *   - site_icp：备案号
 *   - footerPages：后台独立页面里勾了「显示在页脚」的那些（隐私、版权等）
 * 站点名、版本号、品牌署名一律不在这里显示。
 * 新版本提示是后台专属，写在 layouts/admin.blade.php 里，不经过这个文件。
 *
 * 各段之间用点号连接；用 $__fmHasPrev 记录前面是否已有内容，
 * 保证只有一段时不出点号、独立页面单独出现时也不留孤点。
 *
 * 用法：@include('partials.footer_meta')                         不带独立页面
 *       @include('partials.footer_meta', ['footerPages' => $pages])  带独立页面
 */
$__fmBrand = trim((string) ($siteSettings['site_footer'] ?? ''));
$__fmIcp   = trim((string) ($siteSettings['site_icp'] ?? ''));
$__fmPages = $footerPages ?? [];
$__fmHasPrev = false;
?>
@if ($__fmBrand !== '')
    <span class="fm-brand">{{ $__fmBrand }}</span>
    <?php $__fmHasPrev = true; ?>
@endif
@if ($__fmIcp !== '')
    @if ($__fmHasPrev)<span class="dot">·</span>@endif<span class="fm-icp">{{ $__fmIcp }}</span>
    <?php $__fmHasPrev = true; ?>
@endif
@foreach ($__fmPages as $__fmPage)
    @if ($__fmHasPrev)<span class="dot fm-page-dot">·</span>@endif<a class="fm-page-link" href="{{ url('p/' . (string) $__fmPage['slug']) }}">{{ (string) $__fmPage['title'] }}</a>
    <?php $__fmHasPrev = true; ?>
@endforeach
