<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 夜间模式定时切换契约。
 * 后台设置开关 + 起止时间，注入 <html> data 属性，app.js 按时间切换；
 * 用户手动选过主题则以用户为准。
 */
class ThemeScheduleContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_settings_keys_registered(): void
    {
        $ctrl = (string) file_get_contents($this->root() . '/app/Http/Controllers/Admin/SettingController.php');
        $this->assertStringContainsString("'theme_dark_start', 'theme_dark_end'", $ctrl);
        $this->assertStringContainsString("'theme_auto_enabled'", $ctrl);
        $this->assertStringContainsString("'/^([01]?\\d|2[0-3]):[0-5]\\d$/'", $ctrl, '时间格式要校验');

        $defaults = (string) file_get_contents($this->root() . '/app/Core/Setting.php');
        $this->assertStringContainsString("'theme_auto_enabled' => '0'", $defaults);
        $this->assertStringContainsString("'theme_dark_start'   => '22:00'", $defaults);
        $this->assertStringContainsString("'theme_dark_end'     => '07:00'", $defaults);
    }

    public function test_admin_page_has_theme_schedule_controls(): void
    {
        $view = (string) file_get_contents($this->root() . '/resources/views/admin/settings.blade.php');
        $this->assertStringContainsString('<h2>夜间模式</h2>', $view);
        $this->assertStringContainsString('name="theme_auto_enabled"', $view);
        $this->assertStringContainsString('name="theme_dark_start"', $view);
        $this->assertStringContainsString('name="theme_dark_end"', $view);
        $this->assertStringContainsString('手动', $view, '要说明用户手动选择优先');
    }

    public function test_all_layouts_inject_theme_attrs(): void
    {
        foreach (['app', 'admin', 'blank', 'site', 'landing', 'auth'] as $name) {
            $view = (string) file_get_contents($this->root() . "/resources/views/layouts/{$name}.blade.php");
            $this->assertStringContainsString(
                'theme_html_attrs()',
                $view,
                "{$name} 布局没注入主题定时配置"
            );
        }
    }

    public function test_helper_outputs_attrs_only_when_enabled(): void
    {
        \App\Core\Setting::set('theme_auto_enabled', '0');
        $this->assertSame('', theme_html_attrs(), '关闭时不该输出任何属性');

        \App\Core\Setting::set('theme_auto_enabled', '1');
        \App\Core\Setting::set('theme_dark_start', '23:30');
        \App\Core\Setting::set('theme_dark_end', '06:15');
        $attrs = theme_html_attrs();
        \App\Core\Setting::reset();

        $this->assertStringContainsString('data-theme-auto="1"', $attrs);
        $this->assertStringContainsString('data-dark-start="23:30"', $attrs);
        $this->assertStringContainsString('data-dark-end="06:15"', $attrs);
    }

    public function test_js_respects_manual_choice_and_crosses_midnight(): void
    {
        $js = (string) file_get_contents($this->root() . '/public/assets/app.js');
        // 用户手动选过优先
        $this->assertStringContainsString("if (saved === 'dark' || saved === 'light') { return saved; }", $js);
        // 跨零点判断：结束早于开始 → now>=start || now<end
        $this->assertStringContainsString('now >= a || now < b', $js);
        // 每分钟自动核对
        $this->assertStringContainsString('}, 60000);', $js);
        // 只在用户没手动选时自动切
        $this->assertStringContainsString("localStorage.getItem(THEME_KEY) === null", $js);
    }

    public function test_landing_does_not_force_dark_when_auto_on(): void
    {
        $landing = (string) file_get_contents($this->root() . '/resources/views/layouts/landing.blade.php');
        // 原来无脑写死 dark 的逻辑要加 auto 判断，否则自动切换永远失效
        $this->assertStringContainsString("getAttribute('data-theme-auto') === '1'", $landing);
        $this->assertStringContainsString('if (!auto && localStorage.getItem', $landing);
    }
}
