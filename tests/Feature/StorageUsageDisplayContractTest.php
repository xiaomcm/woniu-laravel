<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 个人设置「容量与用量」显示契约。
 * 用户要求：总容量显示「不限」时，也要同时显示已用空间。
 */
class StorageUsageDisplayContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_storage_row_shows_used_even_when_unlimited(): void
    {
        $view = (string) file_get_contents($this->root() . '/resources/views/settings.blade.php');
        $this->assertStringContainsString('存储空间', $view);
        // 已用值必须出现，不能只显示「不限」
        $this->assertStringContainsString('format_bytes($usedBytes)', $view);
        $this->assertStringContainsString("' / 不限'", $view);
        $this->assertStringContainsString('$percent', $view);
        $this->assertStringContainsString('quota-bar', $view);
        // 旧的「已用空间」和「总容量」两行已合并
        $this->assertStringNotContainsString('<span>已用空间</span>', $view);
        $this->assertStringNotContainsString('<span>总容量</span>', $view);
    }

    public function test_unlimited_capacity_still_renders_used_bytes(): void
    {
        $this->app['request'] = \Illuminate\Http\Request::create('http://localhost/');
        $g = \App\Models\Group::allRows()[0] ?? null;
        $this->assertNotNull($g);
        $html = \App\Core\View::render('settings', [
            'pageTitle' => '个人设置',
            'user'      => ['id' => 1, 'name' => '测试', 'email' => 'a@b.c'],
            'group'     => $g,
            'stats'     => ['used' => 12 * 1048576, 'capacity' => 0, 'images' => 3],
            'tokens'    => [],
            'newToken'  => null,
        ], null);
        $this->assertStringContainsString('12 MB / 不限', $html, '不限容量也要显示已用 12 MB');
    }

    public function test_limited_capacity_shows_used_total_and_percent(): void
    {
        $this->app['request'] = \Illuminate\Http\Request::create('http://localhost/');
        $g = \App\Models\Group::allRows()[0] ?? null;
        $this->assertNotNull($g);
        $html = \App\Core\View::render('settings', [
            'pageTitle' => '个人设置',
            'user'      => ['id' => 1, 'name' => '测试', 'email' => 'a@b.c'],
            'group'     => $g,
            'stats'     => ['used' => 25 * 1048576, 'capacity' => 100 * 1048576, 'images' => 3],
            'tokens'    => [],
            'newToken'  => null,
        ], null);
        $this->assertStringContainsString('25 MB / 100 MB（25%）', $html);
    }
}
