<?php

namespace Tests\Feature;

use App\Services\UpdateChecker;
use Tests\TestCase;

/**
 * 新版本检查契约：只提示、不下发，失败静默。
 */
class UpdateCheckerContractTest extends TestCase
{
    private function serviceFile(): string
    {
        $file = dirname(__DIR__, 2) . '/app/Services/UpdateChecker.php';
        $this->assertFileExists($file);
        return (string) file_get_contents($file);
    }

    public function test_check_returns_expected_shape_in_testing(): void
    {
        // 测试环境绝不连外网，这里只验证返回结构稳定
        $r = UpdateChecker::check();
        $this->assertIsArray($r);
        foreach (['has_update', 'latest', 'current', 'url', 'ok'] as $key) {
            $this->assertArrayHasKey($key, $r);
        }
        $this->assertIsBool($r['has_update']);
        $this->assertIsBool($r['ok']);
        // 测试环境没查到版本，就不该提示有更新
        $this->assertFalse($r['has_update']);
    }

    public function test_only_reads_version_never_downloads(): void
    {
        $code = $this->serviceFile();
        // 只请求 releases/latest 接口，不碰 assets / zip / browser_download_url
        $this->assertStringContainsString('releases/latest', $code);
        $this->assertStringNotContainsString('browser_download_url', $code);
        $this->assertStringNotContainsString('.zip', $code);
        $this->assertStringNotContainsString('ZipArchive', $code);
    }

    public function test_network_failures_are_swallowed(): void
    {
        $code = $this->serviceFile();
        $this->assertStringContainsString('catch (\Throwable', $code);
        // 失败要有短 TTL 缓存，避免连不上 GitHub 时每次开后台都卡
        $this->assertStringContainsString('TTL_FAIL', $code);
        $this->assertStringContainsString('environment(\'testing\')', $code);
    }

    public function test_update_hint_is_only_in_admin_layout(): void
    {
        $root = dirname(__DIR__, 2);
        $admin = (string) file_get_contents($root . '/resources/views/layouts/admin.blade.php');
        $footer = (string) file_get_contents($root . '/resources/views/partials/footer_meta.blade.php');

        // 提示只在后台布局渲染，有新版才显示
        $this->assertStringContainsString("\$updateInfo['has_update']", $admin);
        $this->assertStringContainsString('fm-update', $admin);
        // 全站共用的页脚片段里不能有提示，否则前台也会出现
        $this->assertStringNotContainsString('fm-update', $footer);
        $this->assertStringNotContainsString('updateInfo', $footer);
    }
}
