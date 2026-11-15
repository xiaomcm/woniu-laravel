<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 灯箱契约测试。
 *
 * 灯箱是浏览图片的轻量查看器，这里锁死它的入口分布与开关行为，
 * 避免改动后某个页面少了灯箱入口、或开关失效。
 *
 * 这里锁三件事：
 *   1. 灯箱入口出现在哪些页面（少一个页面就是少一块功能）
 *   2. 开关存在且默认开启
 *   3. 资源文件到位、布局引用到位
 */
class LightboxContractTest extends TestCase
{
    /**
     * 必须带灯箱入口的页面。
     *
     * key   = 视图文件名（不含扩展名）
     * value = 该视图里至少要有几个灯箱入口
     */
    private const PAGES = [
        'image_detail' => 2,   // 主图 + 相关推荐
        'explore'      => 1,   // 发现广场
        'user_home'    => 1,   // 用户主页
        'images'       => 1,   // 我的图库
        'album_show'   => 1,   // 相册详情
        'home'         => 1,   // 控制台首页
        'landing'      => 1,   // 落地页展示墙
    ];

    /**
     * 必须引用灯箱资源的布局。
     */
    private const LAYOUTS = ['site', 'app', 'admin', 'landing'];

    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_lightbox_assets_exist(): void
    {
        $root = $this->projectRoot();

        $this->assertFileExists($root . '/public/assets/lightbox.css');
        $this->assertFileExists($root . '/public/assets/lightbox.js');
        $this->assertGreaterThan(1000, filesize($root . '/public/assets/lightbox.css'));
        $this->assertGreaterThan(5000, filesize($root . '/public/assets/lightbox.js'));
    }

    public function test_layouts_load_lightbox_assets(): void
    {
        foreach (self::LAYOUTS as $layout) {
            $file = $this->projectRoot() . "/resources/views/layouts/{$layout}.blade.php";
            $this->assertFileExists($file);
            $code = (string) file_get_contents($file);

            $this->assertStringContainsString(
                "asset('lightbox.css')",
                $code,
                "布局 {$layout} 没有引入 lightbox.css"
            );
            $this->assertStringContainsString(
                "asset('lightbox.js')",
                $code,
                "布局 {$layout} 没有引入 lightbox.js"
            );
        }
    }

    public function test_every_image_page_has_lightbox_entries(): void
    {
        foreach (self::PAGES as $view => $minCount) {
            $file = $this->projectRoot() . "/resources/views/{$view}.blade.php";
            $this->assertFileExists($file, "视图 {$view} 不存在");

            $code = (string) file_get_contents($file);
            $count = substr_count($code, 'data-lightbox-src');

            $this->assertGreaterThanOrEqual(
                $minCount,
                $count,
                "视图 {$view} 的灯箱入口只有 {$count} 个，至少要 {$minCount} 个"
            );
            $this->assertStringContainsString(
                'data-lightbox-caption',
                $code,
                "视图 {$view} 的灯箱入口缺少说明文字"
            );
        }
    }

    public function test_lightbox_switch_is_registered(): void
    {
        // 默认值：开启
        $defaults = \App\Core\Setting::defaults();
        $this->assertArrayHasKey('lightbox_enabled', $defaults);
        $this->assertSame('1', $defaults['lightbox_enabled']);

        // 后台设置页要有这个开关
        $settings = (string) file_get_contents(
            $this->projectRoot() . '/resources/views/admin/settings.blade.php'
        );
        $this->assertStringContainsString('name="lightbox_enabled"', $settings);

        // 控制器要认这个键，否则保存时会漏掉
        $controller = (string) file_get_contents(
            $this->projectRoot() . '/app/Http/Controllers/Admin/SettingController.php'
        );
        $this->assertStringContainsString("'lightbox_enabled'", $controller);
    }

    /**
     * 灯箱 JS 里不能残留占位实现。
     */
    public function test_lightbox_script_has_no_placeholder(): void
    {
        $code = (string) file_get_contents($this->projectRoot() . '/public/assets/lightbox.js');

        $this->assertStringNotContainsString('TODO', $code);
        $this->assertStringNotContainsString('FIXME', $code);
        $this->assertStringContainsString('data-lightbox-src', $code);
        $this->assertStringContainsString('ArrowLeft', $code);
        $this->assertStringContainsString('ArrowRight', $code);
        $this->assertStringContainsString('Escape', $code);
    }
}
