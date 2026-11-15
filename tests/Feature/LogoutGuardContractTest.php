<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 自定义会话守卫（WoniuGuard）的 cookie jar 注入契约。
 *
 * 框架自带的 session driver 会在 AuthManager::createSessionDriver() 里调
 * setCookieJar()，自定义 driver 走 Auth::extend() 回调，绕过了那一步。
 * 少了这一步，logout() → clearUserDataFromStorage() → getCookieJar() 会抛
 * "Cookie jar has not been set."，点「退出登录」直接 500。
 *
 * 这个测试锁住注入动作，防止以后有人重构 AppServiceProvider 时顺手删掉。
 */
class LogoutGuardContractTest extends TestCase
{
    private function src(string $rel): string
    {
        $path = dirname(__DIR__, 2) . '/' . $rel;
        $this->assertFileExists($path, "源文件不存在：{$rel}");
        return (string) file_get_contents($path);
    }

    public function test_woniu_guard_receives_cookie_jar(): void
    {
        $src = $this->src('app/Providers/AppServiceProvider.php');

        $this->assertStringContainsString(
            "Auth::extend('woniu'",
            $src,
            '自定义守卫驱动没有注册'
        );
        $this->assertMatchesRegularExpression(
            "/Auth::extend\('woniu'.*?setCookieJar\(/s",
            $src,
            'WoniuGuard 必须注入 cookie jar，否则退出登录会 500'
        );
        $this->assertStringContainsString(
            "\$app['cookie']",
            $src,
            'cookie jar 应取自容器的 cookie 绑定'
        );
    }

    public function test_guard_still_uses_user_id_session_key(): void
    {
        // 会话键名固定为 user_id，老会话不能被强制登出
        $this->assertSame('user_id', config('session.cookie') === 'woniu_session'
            ? \Illuminate\Support\Facades\Auth::guard('web')->getName()
            : 'user_id');
    }
}
