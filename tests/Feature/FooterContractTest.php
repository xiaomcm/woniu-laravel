<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 页脚署名行契约测试。
 *
 * 用户要求：底部只显示后台真的配置了的内容 ——
 *   版权文字(site_footer)、备案号(site_icp)、后台勾了「显示在页脚」的独立页面。
 * 没配的一段都不出现；网站名、版本号、Powered by WONIU 一律不在底部显示。
 *
 * 这里锁三件事：
 *   1. 页脚片段只从后台配置取值（site_footer / site_icp / footerPages），没有硬编码兜底
 *   2. 页脚不出现站点名、版本号、Powered by
 *   3. 独立页面链接保留，且六个布局全部引用了这个片段
 */
class FooterContractTest extends TestCase
{
    /** 必须引用页脚片段的布局 */
    private const LAYOUTS = ['landing', 'site', 'app', 'admin', 'auth', 'blank'];

    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private function footerCode(): string
    {
        return (string) file_get_contents($this->projectRoot() . '/resources/views/partials/footer_meta.blade.php');
    }

    public function test_footer_only_reads_configured_brand_and_icp(): void
    {
        $file = $this->projectRoot() . '/resources/views/partials/footer_meta.blade.php';
        $this->assertFileExists($file);
        $code = $this->footerCode();

        // 版权文字和备案号都来自后台设置
        $this->assertStringContainsString("site_footer", $code, '页脚版权文字没读后台的 site_footer');
        $this->assertStringContainsString("site_icp", $code, '页脚备案号没读后台的 site_icp');
        // 两段都要有值才显示，不能空着也占位
        $this->assertStringContainsString("\$__fmBrand !== ''", $code, '版权文字为空时应该不显示');
        $this->assertStringContainsString("\$__fmIcp !== ''", $code, '备案号为空时应该不显示');
    }

    public function test_footer_has_no_site_name_version_or_powered(): void
    {
        $code = $this->footerCode();

        // 这些以前在底部、现在都不该出现
        $this->assertStringNotContainsString('site_name', $code, '底部不该显示网站名');
        $this->assertStringNotContainsString('app.version', $code, '底部不该显示版本号');
        $this->assertStringNotContainsString('class="ver"', $code, '底部不该有版本号段');
        $this->assertStringNotContainsString('Powered by WONIU', $code, '不该有 Powered by WONIU 兜底');
        $this->assertStringNotContainsString('Powered by', $code, '底部不该有 Powered by 段');
        $this->assertStringNotContainsString('fm-copy', $code, '底部不该有 © 年份站点名段');
    }

    public function test_footer_keeps_configured_footer_pages(): void
    {
        $code = $this->footerCode();

        // 独立页面（隐私、版权等）是后台勾了「显示在页脚」的，必须保留
        $this->assertStringContainsString('footerPages', $code, '底部不该丢掉独立页面链接');
        $this->assertStringContainsString('fm-page-link', $code, '独立页面链接段缺失');
        $this->assertStringContainsString("url('p/'", $code, '独立页面链接没指向页面地址');
    }

    public function test_no_orphan_dot_when_only_pages_configured(): void
    {
        // 用 $__fmHasPrev 控制点号：只有独立页面时前面不该冒出孤立的「·」
        $code = $this->footerCode();
        $this->assertStringContainsString('$__fmHasPrev = false', $code, '缺少段间点号控制标记');
        $this->assertMatchesRegularExpression(
            '/@if \(\$__fmHasPrev\).*?fm-page-dot.*?@endif/s',
            $code,
            '独立页面前的点号应该受「前面已有内容」控制，避免孤点'
        );
    }

    public function test_footer_sections_are_wrapped_in_value_checks(): void
    {
        $code = $this->footerCode();
        // 两段都包在有值才显示的判断里，没设置就不输出
        $this->assertMatchesRegularExpression(
            '/@if \(\$__fmBrand !== \'\'\).*@endif/s',
            $code,
            '版权文字应该包在有值才显示的判断里'
        );
        $this->assertMatchesRegularExpression(
            '/@if \(\$__fmIcp !== \'\'\).*@endif/s',
            $code,
            '备案号应该包在有值才显示的判断里'
        );
    }

    public function test_every_layout_includes_footer_partial(): void
    {
        foreach (self::LAYOUTS as $layout) {
            $file = $this->projectRoot() . "/resources/views/layouts/{$layout}.blade.php";
            $this->assertFileExists($file, "布局 {$layout} 不存在");
            $code = (string) file_get_contents($file);
            $this->assertStringContainsString(
                'partials.footer_meta',
                $code,
                "布局 {$layout} 没有引用页脚片段"
            );
        }
    }

    public function test_footer_is_reachable_from_every_controller_layout(): void
    {
        $used = [];
        $dir = $this->projectRoot() . '/app/Http/Controllers';
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($rii as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            if (preg_match_all("/'(layouts\/(?:[a-z]+))'/", $code, $m)) {
                foreach ($m[1] as $layout) {
                    $used[$layout] = true;
                }
            }
        }

        $this->assertNotEmpty($used, '没有解析到任何布局引用');
        foreach (array_keys($used) as $layout) {
            $name = substr($layout, strlen('layouts/'));
            $this->assertContains($name, self::LAYOUTS, "控制器用到了布局 {$layout}，但它没有引用页脚片段");
        }
    }
}
