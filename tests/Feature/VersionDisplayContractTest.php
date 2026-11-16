<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 版本号显示契约测试。
 *
 * 用户最新要求：页脚只显示后台设置的版权和备案，版本号不再出现在页脚。
 * config 里的版本号仍要保留 x.y.z 格式 —— 发版脚本靠它递增。
 */
class VersionDisplayContractTest extends TestCase
{
    private function viewFile(string $rel): string
    {
        $file = dirname(__DIR__, 2) . '/resources/views/' . $rel;
        $this->assertFileExists($file, "视图缺失: {$rel}");
        return (string) file_get_contents($file);
    }

    public function test_footer_does_not_show_version(): void
    {
        $code = $this->viewFile('partials/footer_meta.blade.php');

        // 页脚只放后台设置的版权和备案，不再读版本号、不再输出版本号段
        $this->assertStringNotContainsString(
            'app.version',
            $code,
            '页脚不该再读版本号，用户要求底部只留版权和备案'
        );
        $this->assertStringNotContainsString('class="ver"', $code, '页脚不该再有版本号那一段');
    }

    public function test_admin_update_hint_is_outside_footer_partial(): void
    {
        $admin = $this->viewFile('layouts/admin.blade.php');
        $footer = $this->viewFile('partials/footer_meta.blade.php');

        // 新版本提示是后台专属，写在 admin 布局里
        $this->assertStringContainsString('UpdateChecker', $admin, '后台应该接上新版本检查');
        $this->assertStringContainsString('fm-update', $admin, '后台应该有更新提示那一段');
        // 前台共用的 footer_meta 里绝不能有更新提示，否则前台也会显示
        $this->assertStringNotContainsString(
            'fm-update',
            $footer,
            '更新提示不能放进全站共用的页脚片段，只能留在管理后台'
        );
        // 后台侧栏版本号那行早删了
        $this->assertStringNotContainsString('sidebar-ver', $admin, '后台侧栏不该有版本号');
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
