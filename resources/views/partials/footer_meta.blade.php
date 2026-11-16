<?php
/**
 * 全站统一页脚署名行。
 *
 * 内容固定三段，任何页面都一致：
 *   © 年份 站点名 · v版本号 · Powered by WONIU · Powered by 站点名
 *
 * 版本号读 config/defaults.php 的 version（发版时由 tools/release.php 改写），
 * 不在模板里写死，免得改了版本号页脚还显示旧的。
 * 中间那段读后台「系统设置 → 页脚文字」（site_footer），为空时回落到默认的
 * Powered by WONIU，保证这条信息在任何配置下都不会消失。
 * 独立页面链接（footerPages）与备案号（site_icp）作为附加项插在中间。
 *
 * 每段都带 .fm-* 标记：窄屏由 CSS 收起重复的站点名与独立页面，备案号单独走一行，
 * 避免一堆点号在手机上挤成好几行。点号也带对应标记，收起时一起去掉，不留孤点。
 *
 * 更新提示：后台布局会传 $updateInfo（UpdateChecker 的结果），有新版时在版本号后
 * 多显示一段「发现新版本 vX.Y.Z」链接。前台不传这个变量，就完全没有这段。
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
$__fmUpdate = $updateInfo ?? [];

// 产品版本号：跟 config/defaults.php 的 version 同一个源，发版脚本会同步改
$__fmVer = trim((string) \App\Core\Config::get('app.version', ''));
?>
<span class="fm-copy">&copy; {!! date('Y') !!} {{ $__fmSite }}</span>
@if ($__fmVer !== '')
    <span class="dot">·</span><span class="ver">v{{ $__fmVer }}</span>
@endif
@if (!empty($__fmUpdate['has_update']))
    <span class="dot fm-update-dot">·</span><a class="fm-update" href="{{ (string) ($__fmUpdate['url'] ?? '') }}" target="_blank" rel="noopener" title="点开查看 GitHub 新版本">发现新版本 v{{ (string) ($__fmUpdate['latest'] ?? '') }}</a>
@endif
@foreach ($__fmPages as $__fmPage)
    <span class="dot fm-page-dot">·</span><a class="fm-page-link" href="{{ url('p/' . (string) $__fmPage['slug']) }}" style="color:inherit">{{ (string) $__fmPage['title'] }}</a>
@endforeach
<span class="dot">·</span><span class="fm-brand">{{ $__fmBrand }}</span>
@if (!empty($siteSettings['site_icp']))
    <span class="dot fm-icp-dot">·</span><span class="fm-icp">{{ (string) $siteSettings['site_icp'] }}</span>
@endif
<span class="dot fm-powered-dot">·</span><span class="fm-powered">Powered by {{ $__fmSite }}</span>
