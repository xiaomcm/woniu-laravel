<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 版本号显示契约测试。
 *
 * 用户要求：后台版本号统一在底部版权处显示（侧栏那行重复的已删）。
 *
 * 锁三件事：
 *   1. 页脚不再硬编码版本号，而是读 config 的 app.version
 *   2. 后台底部引入 footer_meta、侧栏不再有版本号，并接上更新提示
 *   3. config/defaults.php 里的 version 格式是 x.y.z（发版脚本要解析它）
 */
class VersionDisplayContractTest extends TestCase
{
    private function viewFile(string $rel): string
    {
        $file = dirname(__DIR__, 2) . '/resources/views/' . $rel;
        $this->assertFileExists($file, "视图缺失: {$rel}");
        return (string) file_get_contents($file);
    }

    public function test_footer_reads_version_from_config(): void
    {
        $code = $this->viewFile('partials/footer_meta.blade.php');

        $this->assertStringContainsString(
            'app.version',
            $code,
            '页脚版本号应该读配置里的 app.version，不要写死在模板里'
        );
        $this->assertStringContainsString('ver', $code, '页脚没有版本号的那一段');
    }

    public function test_footer_no_hardcoded_version(): void
    {
        $code = $this->viewFile('partials/footer_meta.blade.php');

        // 老写法是 $__fmVer = '2.0'，写死版本号会让页脚永远显示旧版
        $this->assertDoesNotMatchRegularExpression(
            "/\\\$__fmVer\s*=\s*'[0-9]/",
            $code,
            '页脚版本号又被写死了，应该读配置'
        );
    }

    public function test_admin_shows_version_in_footer_not_sidebar(): void
    {
        $code = $this->viewFile('layouts/admin.blade.php');

        // 版本号统一走底部版权（footer_meta），侧栏那行重复的已删掉
        $this->assertStringNotContainsString(
            'sidebar-ver',
            $code,
            '后台侧栏不该再有版本号，统一到底部版权处显示'
        );
        $this->assertStringContainsString(
            "include('partials.footer_meta'",
            $code,
            '后台底部没有引入统一的版权行（版本号在那里显示）'
        );
        // 版本号从配置读这条仍由 footer_meta 契约保证，这里只确认后台接了更新提示数据
        $this->assertStringContainsString(
            'UpdateChecker',
            $code,
            '后台应该在底部版权处接上新版本提示'
        );
    }

    public function test_defaults_version_is_semver(): void
    {
        $file = dirname(__DIR__, 2) . '/config/defaults.php';
        $code = (string) file_get_contents($file);

        $this->assertMatchesRegularExpression(
            "/'version'\s*=>\s*'\d+\.\d+\.\d+'/",
            $code,
            "config/defaults.php 的 version 必须是 x.y.z，发版脚本要按这个格式解析"
        );
    }
}
