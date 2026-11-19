<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 妖火账号 OAuth2 登录契约。
 * 端点/字段映射写死在驱动里，管理员只填 Client ID / Secret；
 * 无邮箱由 OauthController 兜底生成 @oauth.local。
 */
class YaohuoLoginContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_driver_uses_yaohuo_oauth2_endpoints(): void
    {
        $code = (string) file_get_contents($this->root() . '/app/Drivers/Social/Yaohuo.php');
        $this->assertStringContainsString("'https://yaohuo.me/OAuth/Authorize.aspx'", $code);
        $this->assertStringContainsString("'https://yaohuo.me/OAuth/Token.aspx'", $code);
        $this->assertStringContainsString("'https://yaohuo.me/OAuth/Profile.aspx'", $code);
        // OAuth2 只能请求 profile scope，不能带 openid（那是 OIDC）
        $this->assertStringContainsString("'scope'         => 'profile'", $code);
        $this->assertStringNotContainsString('openid profile', $code);
        // 身份字段是 userid / nickname（不是 OIDC 的 sub）
        $this->assertStringContainsString("\$user['userid']", $code);
        $this->assertStringContainsString("\$user['nickname']", $code);
        // openid 加前缀防止和其它源的数字 id 撞号
        $this->assertStringContainsString("'yh_' .", $code);
    }

    public function test_driver_registered_with_only_two_fields(): void
    {
        $p = \App\Drivers\Registry::provider('social', 'yaohuo');
        $this->assertNotNull($p);
        $this->assertSame('妖火账号', $p['name']);
        $this->assertSame(\App\Drivers\Social\Yaohuo::class, $p['class']);
        $this->assertSame(['client_id', 'client_secret'], array_column($p['fields'], 'key'));
    }

    public function test_no_email_falls_back_to_placeholder(): void
    {
        // 妖火 Profile 无邮箱，驱动返回空 email，由 OauthController 兜底
        $code = (string) file_get_contents($this->root() . '/app/Drivers/Social/Yaohuo.php');
        $this->assertStringContainsString("'email'    => ''", $code);
        $oauth = (string) file_get_contents($this->root() . '/app/Http/Controllers/OauthController.php');
        $this->assertStringContainsString("\$email === '' || !filter_var(\$email, FILTER_VALIDATE_EMAIL)", $oauth);
        $this->assertStringContainsString('@oauth.local', $oauth);
    }

    public function test_login_page_shows_social_buttons(): void
    {
        $this->app['request'] = \Illuminate\Http\Request::create('http://localhost/');
        $html = \App\Core\View::render('auth/login', [
            'pageTitle' => '登录',
            'redirect'  => '/',
            'error'     => '',
            'ok'        => '',
            'socials'   => [['id' => 9, 'name' => '妖火账号']],
        ], null);
        $this->assertStringContainsString('第三方账号登录', $html);
        $this->assertStringContainsString('妖火账号', $html);
        $this->assertStringContainsString('/oauth/9', $html);
    }

    public function test_login_form_passes_enabled_socials(): void
    {
        $code = (string) file_get_contents($this->root() . '/app/Http/Controllers/AuthController.php');
        $this->assertStringContainsString("Driver::active('social')", $code);
        $this->assertStringContainsString("'socials'   => \$socials", $code);
        $this->assertStringContainsString("Setting::bool('oauth_enabled', true)", $code);
    }

    public function test_profile_fallback_to_token_user_info(): void
    {
        $code = (string) file_get_contents($this->root() . '/app/Drivers/Social/Yaohuo.php');
        // 文档说 token 响应可能带 user_info，Profile 失败时用它兜底
        $this->assertStringContainsString("\$tokenJson['user_info']", $code);
    }
}
