<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 找回密码契约：邮箱验证码重置，防枚举、限流、一次性、重置后跳登录。
 */
class ForgotPasswordContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_forgot_routes_registered(): void
    {
        $routes = (string) file_get_contents($this->root() . '/tests/Fixtures/routes-2.0.txt');
        foreach (['GET /forgot', 'POST /forgot/send', 'GET /forgot/reset', 'POST /forgot/reset'] as $r) {
            $this->assertStringContainsString($r, $routes, "缺少路由 {$r}");
        }
    }

    public function test_send_does_not_reveal_account_existence(): void
    {
        $code = (string) file_get_contents($this->root() . '/app/Http/Controllers/AuthController.php');
        // 邮箱不存在时也回成功提示，防枚举
        $this->assertStringContainsString('if ($user !== null)', $code);
        $this->assertStringContainsString('如果该邮箱已注册，验证码已发送', $code);
        // 复用 CodeService（限流+过期+尝试次数）
        $this->assertStringContainsString("CodeService::send('mail', \$email, 'forgot')", $code);
    }

    public function test_reset_verifies_code_and_session_email(): void
    {
        $code = (string) file_get_contents($this->root() . '/app/Http/Controllers/AuthController.php');
        $this->assertStringContainsString("CodeService::verify('mail', \$email, 'forgot', \$code)", $code);
        // 会话里的 user_id 与邮箱要一致，防止换码攻击
        $this->assertStringContainsString("strcasecmp((string) \$user['email'], \$email) !== 0", $code);
        $this->assertStringContainsString("mb_strlen(\$password) < 6", $code);
        $this->assertStringContainsString("\$password !== \$confirm", $code);
        // 成功后清会话、跳登录
        $this->assertStringContainsString("Session::forget('_forgot_reset')", $code);
        $this->assertStringContainsString("Session::put('_login_ok'", $code);
        $this->assertStringContainsString("redirect('/login')", $code);
    }

    public function test_views_and_login_link_exist(): void
    {
        $root = $this->root() . '/resources/views/auth/';
        foreach (['forgot.blade.php', 'forgot-reset.blade.php'] as $f) {
            $this->assertFileExists($root . $f);
        }
        $login = (string) file_get_contents($root . 'login.blade.php');
        $this->assertStringContainsString("url('forgot')", $login, '登录页要有忘记密码入口');
        $this->assertStringContainsString('alert-success', $login, '重置成功提示要显示');
    }

    public function test_mail_must_be_enabled(): void
    {
        $code = (string) file_get_contents($this->root() . '/app/Http/Controllers/AuthController.php');
        $this->assertStringContainsString("Setting::bool('mail_enabled', false)", $code);
        $this->assertStringContainsString('站点未开启邮件服务', $code);
    }
}
