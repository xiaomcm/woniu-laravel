<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台名称自定义（admin_title）契约测试。
 *
 * 后台左上角的品牌名原来硬编码成「{站点名} 后台」，用户要求能自定义，
 * 且设置项要放在「系统设置 → 站点描述」后面。
 *
 * 语义：admin_title 留空 = 跟随站点名（显示「{站点名} 后台」）。
 *
 * 这里锁四件事：
 *   1. Setting::defaults() 里有 admin_title，默认空串
 *   2. 设置保存白名单里有 admin_title（否则存不进去）
 *   3. 后台设置页有该输入框，且排在 site_description 之后
 *   4. 后台布局的 brand-name 用的是 $brandName（不是写死的「{站点名} 后台」）
 */
class AdminTitleContractTest extends TestCase
{
    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * 本仓库「设置类 / 设置控制器 / 设置页 / 后台布局」路径。
     *
     * @return array<string, array{string, string, string, string}>
     */
    private function targets(): array
    {
        return [
            '本仓库' => [
                $this->projectRoot() . '/app/Core/Setting.php',
                $this->projectRoot() . '/app/Http/Controllers/Admin/SettingController.php',
                $this->projectRoot() . '/resources/views/admin/settings.blade.php',
                $this->projectRoot() . '/resources/views/layouts/admin.blade.php',
            ],
        ];
    }

    public function test_default_value_exists_and_is_empty(): void
    {
        foreach ($this->targets() as $label => $files) {
            $settingFile = $files[0];
            $this->assertFileExists($settingFile, "{$label} 的 Setting.php 不存在");
            $code = (string) file_get_contents($settingFile);

            $this->assertMatchesRegularExpression(
                "/'admin_title'\s*=>\s*''/",
                $code,
                "{$label} 的 Setting::defaults() 里没有 admin_title（默认应为空串）"
            );
        }
    }

    public function test_save_allowlist_contains_admin_title(): void
    {
        foreach ($this->targets() as $label => $files) {
            $controllerFile = $files[1];
            $this->assertFileExists($controllerFile, "{$label} 的 SettingController.php 不存在");
            $code = (string) file_get_contents($controllerFile);

            $this->assertMatchesRegularExpression(
                "/'admin_title'/",
                $code,
                "{$label} 的保存白名单里没有 admin_title，后台存不进去"
            );

            // 必须在 TEXT_KEYS 里，不能是 NUMBER/BOOL
            if (preg_match('/TEXT_KEYS\s*=\s*\[(.*?)\];/s', $code, $m)) {
                $this->assertStringContainsString(
                    "'admin_title'",
                    $m[1],
                    "{$label} 的 admin_title 不在 TEXT_KEYS 里"
                );
            }
        }
    }

    public function test_settings_page_has_field_after_site_description(): void
    {
        foreach ($this->targets() as $label => $files) {
            $viewFile = $files[2];
            $this->assertFileExists($viewFile, "{$label} 的设置页不存在");
            $code = (string) file_get_contents($viewFile);

            $this->assertStringContainsString(
                'name="admin_title"',
                $code,
                "{$label} 的设置页没有 admin_title 输入框"
            );

            $descPos = strpos($code, 'name="site_description"');
            $titlePos = strpos($code, 'name="admin_title"');
            $this->assertNotFalse($descPos, "{$label} 的设置页没有 site_description");
            $this->assertNotFalse($titlePos, "{$label} 的设置页没有 admin_title");
            $this->assertGreaterThan(
                $descPos,
                $titlePos,
                "{$label} 的「后台名称」没有排在「站点描述」后面"
            );
        }
    }

    public function test_admin_layout_uses_custom_brand_name(): void
    {
        foreach ($this->targets() as $label => $files) {
            $layoutFile = $files[3];
            $this->assertFileExists($layoutFile, "{$label} 的后台布局不存在");
            $code = (string) file_get_contents($layoutFile);

            // 必须算 $brandName：自定义优先，留空回落站点名
            $this->assertStringContainsString(
                'admin_title',
                $code,
                "{$label} 的后台布局没有读 admin_title"
            );
            $this->assertStringContainsString(
                '$brandName',
                $code,
                "{$label} 的后台布局没有算出 \$brandName"
            );

            // brand-name 必须用 $brandName，而不是写死的「{站点名} 后台」
            // Blade 插值写法要匹配
            $this->assertMatchesRegularExpression(
                '/<span class="brand-name">.*\$brandName.*<\/span>/s',
                $code,
                "{$label} 的 brand-name 没有用 \$brandName"
            );

            // 不能再有写死的「$siteName }} 后台」
            $this->assertStringNotContainsString(
                '$siteName }} 后台',
                $code,
                "{$label} 的 brand-name 还写死着「站点名 后台」，自定义不生效"
            );
        }
    }

    public function test_brand_name_has_min_width_zero_so_ellipsis_works(): void
    {
        // 长名字不能溢出侧栏：flex 子项要能收缩，text-overflow 才生效
        $files = [
            '本仓库' => $this->projectRoot() . '/public/assets/app.css',
        ];

        foreach ($files as $label => $file) {
            $this->assertFileExists($file, "{$label} 的 app.css 不存在");
            $css = (string) file_get_contents($file);

            $this->assertMatchesRegularExpression(
                '/\.brand-name\s*\{[^}]*min-width\s*:\s*0/',
                $css,
                "{$label} 的 .brand-name 缺 min-width:0，自定义长名字会溢出侧栏"
            );
            $this->assertMatchesRegularExpression(
                '/\.brand-name\s*\{[^}]*text-overflow\s*:\s*ellipsis/',
                $css,
                "{$label} 的 .brand-name 缺 text-overflow:ellipsis"
            );
        }
    }
}
