<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 主页与控制台隔离契约。
 *
 * 需求：登录账号以后不该还能打开主页（/）。以前主页和控制台共用 / 一个 URL，
 * 靠登录态在同一个地址里切换视图，结果「登录后依然能打开主页」在 URL 层面成立。
 *
 * 现在拆成两个独立地址：
 *   /          → 营销落地页，游客专属；登录用户访问一律 302 弹走
 *   /dashboard → 用户控制台，登录专属；未登录跳登录页
 *
 * 这个测试锁死三件事：
 *   1. 两个地址各自注册了正确的控制器方法
 *   2. 控制器里的登录态判断与跳转方向没被改回去
 *   3. 全站「控制台」入口不再指向 /（否则改回旧行为只是改几个 href）
 */
class HomepageIsolationContractTest extends TestCase
{
    private function src(string $rel): string
    {
        $path = dirname(__DIR__, 2) . '/' . $rel;
        $this->assertFileExists($path, "源文件不存在：{$rel}");
        return (string) file_get_contents($path);
    }

    /**
     * / 与 /dashboard 两条路由必须同时存在，且指向不同方法。
     */
    public function test_home_and_dashboard_are_separate_routes(): void
    {
        $routes = $this->src('routes/web.php');

        $this->assertMatchesRegularExpression(
            "/Route::get\\('\/',\\s*\\[HomeController::class,\\s*'index'\\]\\)/",
            $routes,
            '根路由 / 必须仍指向 HomeController@index'
        );
        $this->assertMatchesRegularExpression(
            "/Route::get\\('\/dashboard',\\s*\\[HomeController::class,\\s*'dashboard'\\]\\)/",
            $routes,
            '缺少 /dashboard 控制台路由'
        );
    }

    /**
     * 控制台页面必须独立成方法，且强制登录。
     */
    public function test_dashboard_method_requires_login(): void
    {
        $src = $this->src('app/Http/Controllers/HomeController.php');

        $this->assertStringContainsString(
            'public function dashboard(',
            $src,
            'HomeController 缺少 dashboard() 方法'
        );
        $this->assertMatchesRegularExpression(
            '/public function dashboard\(.*?Auth::requireLogin\(\)/s',
            $src,
            'dashboard() 必须调用 Auth::requireLogin()，未登录要跳登录页'
        );
        $this->assertStringContainsString(
            "'layouts/app'",
            $src,
            'dashboard() 必须渲染 layouts/app 布局'
        );
    }

    /**
     * 主页方法里：登录用户 302 弹到 /dashboard，游客才渲染落地页。
     */
    public function test_home_redirects_logged_in_users_to_dashboard(): void
    {
        $src = $this->src('app/Http/Controllers/HomeController.php');

        $this->assertMatchesRegularExpression(
            '/public function index\(.*?Auth::check\(\).*?redirect\(\'\/dashboard\'\)/s',
            $src,
            'index() 必须在检测到登录态后重定向到 /dashboard'
        );
        $this->assertMatchesRegularExpression(
            "/public function index\(.*?return \\\$this->view\('landing'/s",
            $src,
            'index() 必须渲染 landing 落地页视图'
        );

        // 控制台视图不能再从 index() 里渲染 —— 那是旧行为（同一个 URL 两种身份）
        $indexBody = $this->sliceMethod($src, 'index');
        $this->assertStringNotContainsString(
            '$this->view(\'home\'',
            $indexBody,
            'index() 里不应再渲染控制台 home 视图'
        );
    }

    /**
     * 全站「控制台」入口一律指向 /dashboard，不得再有指向 / 的控制台链接。
     */
    public function test_console_entry_points_point_to_dashboard(): void
    {
        $files = [
            'resources/views/landing.blade.php',
            'resources/views/layouts/landing.blade.php',
            'resources/views/layouts/app.blade.php',
            'resources/views/layouts/admin.blade.php',
            'resources/views/layouts/site.blade.php',
            'resources/views/pay_result.blade.php',
        ];

        foreach ($files as $file) {
            $src = $this->src($file);

            // 抓出所有锚点标签里 href 指向根路径的「控制台」类文案
            $this->assertDoesNotMatchRegularExpression(
                '/<a[^>]*href="\{\{\s*url\(\'\'\)\s*\}\}"[^>]*>\s*(?:<[^>]+>\s*)*[^<]*(?:控制台|上传图片)/',
                $src,
                "{$file} 里还有指向根路径 / 的控制台入口，应该改成 url('dashboard')"
            );
        }
    }

    /**
     * 落地页的「进入控制台」按钮必须指向 /dashboard。
     */
    public function test_landing_console_button_points_to_dashboard(): void
    {
        $src = $this->src('resources/views/landing.blade.php');

        $this->assertStringContainsString(
            "url('dashboard')",
            $src,
            '落地页「进入控制台」按钮必须指向 /dashboard'
        );

        $layout = $this->src('resources/views/layouts/landing.blade.php');
        $this->assertStringContainsString(
            "url('dashboard')",
            $layout,
            '落地页布局的「进入控制台」按钮必须指向 /dashboard'
        );
    }

    /**
     * 侧边栏「上传图片」原来错指 /（落地页），必须指真正的上传页 /upload。
     */
    public function test_upload_entry_points_point_to_upload_route(): void
    {
        $app = $this->src('resources/views/layouts/app.blade.php');
        $this->assertMatchesRegularExpression(
            '/<a class="nav-item[^"]*" href="\{\{\s*url\(\'upload\'\)\s*\}\}"/',
            $app,
            '侧边栏「上传图片」应指向 /upload，不能指向 /'
        );

        $images = $this->src('resources/views/images.blade.php');
        $this->assertStringContainsString(
            "url('upload')",
            $images,
            '图片管理页的「上传」按钮应指向 /upload'
        );
    }

    /**
     * 登录 / 注册 / 第三方登录成功后都要进控制台，不能再回落地页。
     */
    public function test_login_and_register_redirect_to_dashboard(): void
    {
        $auth = $this->src('app/Http/Controllers/AuthController.php');

        $this->assertStringNotContainsString(
            "redirect('/')",
            $auth,
            'AuthController 里不应再有 redirect(\'/\')，登录后要进 /dashboard'
        );
        $this->assertStringContainsString(
            "redirect('/dashboard')",
            $auth,
            'AuthController 登录/注册成功后必须跳 /dashboard'
        );
        $this->assertStringContainsString(
            "Request::input('redirect', '/dashboard')",
            $auth,
            '登录默认回跳地址应为 /dashboard'
        );

        $oauth = $this->src('app/Http/Controllers/OauthController.php');
        $this->assertStringNotContainsString(
            "redirect('/')",
            $oauth,
            'OauthController 登录成功后不能回落地页'
        );
    }

    /**
     * 取出某个方法的源码片段。
     */
    private function sliceMethod(string $src, string $method): string
    {
        $start = strpos($src, 'public function ' . $method . '(');
        $this->assertNotFalse($start, "找不到方法 {$method}()");

        $next = strpos($src, 'public function ', $start + 10);
        return $next === false
            ? substr($src, $start)
            : substr($src, $start, $next - $start);
    }
}
