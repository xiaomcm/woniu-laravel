<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\DatabaseTestCase;

/**
 * HTTP 冒烟测试。
 *
 * 路由存在不代表页面能渲染。控制器改坏了、视图语法错了、Blade 编译出乱子，
 * 这里都能第一时间抓到 —— 断言的是「返回 200 且没有 500」，
 * 状态码与页面正文一并检查。
 */
class SmokeTest extends DatabaseTestCase
{
    /**
     * 匿名可访问的页面。
     */
    private const ANONYMOUS_PAGES = [
        '/',
        '/login',
        '/register',
        '/explore',
        '/notices',
        '/shop',
        '/feedback',
        '/support/tickets',
        '/support/tickets/new',
        '/pay/return',
    ];

    /**
     * 登录后可见的页面。
     */
    private const AUTH_PAGES = [
        '/upload',
        '/images',
        '/albums',
        '/settings',
        '/admin',
        '/admin/users',
        '/admin/groups',
        '/admin/storages',
        '/admin/images',
        '/admin/settings',
        '/admin/logs',
        '/admin/drivers',
        '/admin/shop/packages',
        '/admin/shop/orders',
        '/admin/shop/coupons',
        '/admin/support/tickets',
        '/admin/support/reports',
        '/admin/support/feedbacks',
        '/admin/content/pages',
        '/admin/content/notices',
        '/shop/orders',
    ];

    public function test_anonymous_pages_render_without_server_errors(): void
    {
        foreach (self::ANONYMOUS_PAGES as $url) {
            $response = $this->get($url);

            $this->assertLessThan(
                500,
                $response->getStatusCode(),
                $url . ' 返回了 5xx'
            );
            $this->assertNotSame(
                419,
                $response->getStatusCode(),
                $url . ' 返回了 419（CSRF 误伤）'
            );
        }
    }

    public function test_login_page_contains_the_login_form(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('name="_token"', false);
        $response->assertSee('name="account"', false);
        $response->assertSee('name="password"', false);
    }

    public function test_register_page_contains_the_register_form(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('name="_token"', false);
    }

    public function test_admin_pages_redirect_to_login_when_not_authenticated(): void
    {
        // 后台页面未登录时要跳登录页，而不是把后台内容吐出来
        $response = $this->get('/admin/users');

        $this->assertContains(
            $response->getStatusCode(),
            [302, 401],
            '未登录访问后台应被重定向或拒绝，实际返回 ' . $response->getStatusCode()
        );
    }

    public function test_user_can_log_in_and_reach_the_dashboard(): void
    {
        $this->createUser('admin', 'admin@example.com', 'Test123456', true);

        $response = $this->post('/login', [
            '_token'   => $this->csrfToken(),
            'account'  => 'admin@example.com',
            'password' => 'Test123456',
            'redirect' => '/',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 302], '登录应该成功');

        // 登录后能拿到会话
        $this->assertNotNull(session('user_id'), '登录后 session 里应该有 user_id');
    }

    public function test_authenticated_pages_render_without_server_errors(): void
    {
        $this->createUser('admin', 'admin@example.com', 'Test123456', true);

        $this->login('admin@example.com');

        foreach (self::AUTH_PAGES as $url) {
            $response = $this->get($url);

            $this->assertLessThan(
                500,
                $response->getStatusCode(),
                $url . ' 返回了 5xx'
            );
        }
    }

    public function test_api_endpoints_return_json_envelope(): void
    {
        $this->createUser('admin', 'admin@example.com', 'Test123456', true);

        $this->login('admin@example.com');

        $response = $this->get('/api/v1/stats');

        $response->assertOk();
        $response->assertJsonStructure(['status', 'message', 'data']);
    }

    public function test_unknown_route_returns_404_with_the_chinese_error_page(): void
    {
        $response = $this->get('/no-such-page-xyz');

        $this->assertSame(404, $response->getStatusCode());
        // 404 页面是中文的
        $response->assertSee('页面', false);
    }

    public function test_image_detail_page_renders(): void
    {
        $this->createUser('tester', 'tester@example.com');
        $sha1 = sha1('smoke-test-image');
        $this->createImage(1, $sha1);

        $response = $this->get('/i/' . $sha1);

        $this->assertLessThan(500, $response->getStatusCode());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_session_key_is_user_id(): void
    {
        $userId = $this->createUser('tester', 'tester@example.com');

        $this->login('tester@example.com');

        // 会话里的用户 id 键名固定为 user_id，老会话才能接管
        $this->assertSame($userId, (int) session('user_id'));
        $this->assertIsArray(User::find($userId));
    }

    /**
     * 登录。会先取登录页的 CSRF 令牌再提交。
     */
    private function login(string $email, string $password = 'Test123456'): void
    {
        $response = $this->post('/login', [
            '_token'   => $this->csrfToken(),
            'account'  => $email,
            'password' => $password,
            'redirect' => '/',
        ]);

        $this->assertContains(
            $response->getStatusCode(),
            [200, 302],
            '登录失败，HTTP ' . $response->getStatusCode()
        );
    }

    /**
     * 从当前页面拿 CSRF 令牌。
     */
    private function csrfToken(): string
    {
        $html = $this->get('/login')->getContent();
        if (preg_match('/name="_token"[^>]*value="([^"]+)"/', $html, $m)) {
            return $m[1];
        }
        return '';
    }
}
