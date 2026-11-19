<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 用户提的四个逻辑问题的修复契约：
 *  1) 站主关了全站 API，用户不能再自己打开
 *  2) 手机端能看到灯箱入口（触屏遮罩常驻）+ 链接入口 touch 拦截
 *  3) 改邮箱验证码 + 后台可控开关
 *  4) 后台编辑弹窗在手机端可滚动（滚动条不被全局规则吃掉）
 */
class FourLogicFixesContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    // ── 1. API 令牌开关 ─────────────────────────────
    public function test_site_api_switch_blocks_user_toggle(): void
    {
        $c = (string) file_get_contents($this->root() . '/app/Http/Controllers/SettingsController.php');
        $this->assertStringContainsString("Setting::bool('api_enabled', true)", $c,
            '开启前必须检查站点总开关');
        $this->assertStringContainsString('本站已关闭 API 接口', $c);
        // 开启开关 + 创建令牌两处都要拦（另有一处是给视图传值）
        $this->assertGreaterThanOrEqual(2, substr_count($c, "Setting::bool('api_enabled', true)"));

        $v = (string) file_get_contents($this->root() . '/resources/views/settings.blade.php');
        $this->assertStringContainsString('siteApiOn', $v);
        $this->assertStringContainsString('disabled', $v);
    }

    // ── 2. 手机端灯箱 ────────────────────────────────
    public function test_touch_devices_show_lightbox_entry(): void
    {
        $css = (string) file_get_contents($this->root() . '/public/assets/app.css');
        $this->assertStringContainsString('@media (hover: none), (pointer: coarse)', $css);
        $this->assertStringContainsString('.image-card .image-overlay { opacity: 1; }', $css);

        $js = (string) file_get_contents($this->root() . '/public/assets/lightbox.js');
        $this->assertStringContainsString("node.tagName === 'A'", $js);
        $this->assertStringContainsString("addEventListener('touchstart'", $js);
        $this->assertStringContainsString('passive: false', $js);
    }

    // ── 3. 邮箱验证码 ────────────────────────────────
    public function test_email_change_verify_is_admin_controllable(): void
    {
        $setting = (string) file_get_contents($this->root() . '/app/Core/Setting.php');
        $this->assertStringContainsString("'email_change_verify' => '1'", $setting);

        $admin = (string) file_get_contents($this->root() . '/app/Http/Controllers/Admin/SettingController.php');
        $this->assertStringContainsString("'email_change_verify'", $admin, '开关要能被后台保存');
        $view = (string) file_get_contents($this->root() . '/resources/views/admin/settings.blade.php');
        $this->assertStringContainsString('name="email_change_verify"', $view);

        $c = (string) file_get_contents($this->root() . '/app/Http/Controllers/SettingsController.php');
        $this->assertStringContainsString('sendEmailChangeCode', $c);
        $this->assertStringContainsString("CodeService::send('mail', \$email, 'email_change')", $c);
        $this->assertStringContainsString("CodeService::verify('mail', \$data['email'], 'email_change', \$code)", $c);
        // 验证失败时不能把邮箱写进去
        // 用行号判断顺序最直接，避免字符串里的 $ 转义踩坑
        $lines = explode("\n", $c);
        $verifyLine = $writeLine = 0;
        foreach ($lines as $i => $line) {
            if (strpos($line, "CodeService::verify('mail'") !== false && $verifyLine === 0) { $verifyLine = $i + 1; }
            if (strpos($line, "User::updateById((int) \$user['id'], ['email'") !== false && $writeLine === 0) { $writeLine = $i + 1; }
        }
        $this->assertGreaterThan(0, $verifyLine, '找到验证逻辑');
        $this->assertGreaterThan(0, $writeLine, '找到写入逻辑');
        $this->assertLessThan($writeLine, $verifyLine, '必须先验证再写入');

        $routes = (string) file_get_contents($this->root() . '/routes/web.php');
        $this->assertStringContainsString("'/settings/email-code'", $routes);
        $this->assertStringContainsString('POST /settings/email-code',
            (string) file_get_contents($this->root() . '/tests/Fixtures/routes-2.0.txt'));
    }

    // ── 4. 后台弹窗手机端滚动 ────────────────────────
    public function test_modal_body_keeps_scrollbar_on_mobile(): void
    {
        $css = (string) file_get_contents($this->root() . '/public/assets/app.css');
        // 全局藏了滚动条，弹窗必须显式恢复（scrollbar-width 会继承）
        $this->assertStringContainsString('html::-webkit-scrollbar { width: 0; height: 0; }', $css);
        $this->assertStringContainsString('.modal-body::-webkit-scrollbar', $css);
        $this->assertStringContainsString('scrollbar-width: thin', $css);
        $this->assertStringContainsString('-webkit-overflow-scrolling: touch', $css);
        // 手机端提高表单区高度
        $this->assertStringContainsString('@media (max-width: 640px)', $css);
        $this->assertStringContainsString('.modal-body { max-height: 72vh', $css);
    }
}
