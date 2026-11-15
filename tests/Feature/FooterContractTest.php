<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 页脚署名行契约测试。
 *
 * 「© 年份 站点名 · Powered by WONIU · Powered by 站点名 版本」这三段
 * 必须在全站每个布局里都出现 —— 落地页、发现广场、用户中心、后台、
 * 登录注册、分享页，一个都不能少。
 *
 * 这里锁三件事：
 *   1. 页脚片段文件存在，且包含三段固定内容
 *   2. 六个布局全部引用了这个片段（不允许某个布局自己写一份）
 *   3. 片段里的版本号与产品版本一致
 */
class FooterContractTest extends TestCase
{
    /**
     * 必须引用页脚片段的布局（Blade 实现）。
     */
    private const LAYOUTS = ['landing', 'site', 'app', 'admin', 'auth', 'blank'];

    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_footer_partial_exists_and_has_three_parts(): void
    {
        $file = $this->projectRoot() . '/resources/views/partials/footer_meta.blade.php';
        $this->assertFileExists($file);

        $code = (string) file_get_contents($file);

        // 版权段
        $this->assertStringContainsString('&copy;', $code, '页脚缺少版权段');
        // 品牌段：后台没配 site_footer 时的兜底
        $this->assertStringContainsString('Powered by WONIU', $code, '页脚缺少 Powered by WONIU 兜底');
        // 站点名来自配置，不能写死
        $this->assertStringContainsString("site_name", $code, '页脚的站点名没有读配置');
        // 版本段：读配置里的产品版本，不能写死（发版时版本号会变）
        $this->assertStringContainsString('app.version', $code, '页脚的版本号没有读配置');
        $this->assertStringContainsString('Powered by', $code, '页脚缺少版本段');
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
                "布局 {$layout} 没有引用页脚片段，页脚署名会缺失"
            );
        }
    }

    public function test_footer_is_reachable_from_every_controller_layout(): void
    {
        // 控制器里实际用到的布局，必须都在上面的清单里
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
            $this->assertContains(
                $name,
                self::LAYOUTS,
                "控制器用到了布局 {$layout}，但它没有引用页脚片段"
            );
        }
    }
}
