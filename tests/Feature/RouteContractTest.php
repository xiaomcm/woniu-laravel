<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 路由契约测试。
 *
 * 锁死本仓库的路由表：方法 + 路径 + 参数约束，一个都不能少、不能多。
 * 路由表是功能入口的直接清单 —— 少一条路由就等于少一个功能入口。
 *
 * 基准是 tests/Fixtures/routes-2.0.txt。参数约束的归一化规则：
 *   {id:num}   ↔ {id} + where('id', '[0-9]+')
 *   {key:slug} ↔ {key} + where('key', '[A-Za-z0-9_\-]+')
 *   {path:any} ↔ {path} + where('path', '.+')
 */
class RouteContractTest extends TestCase
{
    /**
     * Laravel 框架自带、非业务用途的路由，不参与「多出路由」的比对。
     */
    private const FRAMEWORK_ROUTES = [
        'GET /up',
    ];

    /**
     * 框架自带的合法新增路由。
     *
     * 安装向导做成 /install 路由，接在中间件与 CSRF 豁免里。
     */
    private const V2_ADDITIONS = [
        'GET /install',
        'POST /install',
        // API 功能：个人开关 + 用法文档页
        'POST /settings/api-enabled',
        'GET /api-docs',
        // 改邮箱验证码（后台可控）
        'POST /settings/email-code',
        // 图片直链短地址（不带 uploads 前缀）
        'GET /{path}',
    ];

    /**
     * 读取路由契约基准。
     */
    private function contractRoutes(): array
    {
        $file = __DIR__ . '/../Fixtures/routes-2.0.txt';
        $this->assertFileExists($file, '路由契约基准文件缺失');

        $routes = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $routes[] = $line;
        }
        return $routes;
    }

    /**
     * 把 Laravel 路由归一化成带参数约束的写法。
     */
    private function normalize(string $uri, array $wheres): string
    {
        $type = static function (string $constraint): string {
            $constraint = trim($constraint);
            if ($constraint === '[0-9]+' || $constraint === '\\d+') {
                return 'num';
            }
            if ($constraint === '[A-Za-z0-9_\\-]+') {
                return 'slug';
            }
            if ($constraint === '.+') {
                return 'any';
            }
            return '';
        };

        $out = preg_replace_callback(
            '/\{(\w+)\}/',
            static function (array $m) use ($wheres, $type): string {
                if (!isset($wheres[$m[1]])) {
                    return $m[0];
                }
                $t = $type((string) $wheres[$m[1]]);
                return $t === '' ? $m[0] : '{' . $m[1] . ':' . $t . '}';
            },
            $uri
        );

        return (string) $out;
    }

    /**
     * 拿到实际注册的全部「方法 + 路径」。
     */
    private function actualRoutes(): array
    {
        $routes = [];
        foreach (Route::getRoutes() as $route) {
            $uri = '/' . ltrim($route->uri(), '/');
            if ($uri === '//') {
                $uri = '/';
            }
            $pattern = $this->normalize($uri, $route->wheres);

            foreach ($route->methods() as $method) {
                if ($method === 'OPTIONS' || $method === 'HEAD') {
                    continue;
                }
                $routes[] = $method . ' ' . $pattern;
            }
        }
        return array_values(array_unique($routes));
    }

    public function test_every_contract_route_exists(): void
    {
        $actual = $this->actualRoutes();
        $missing = array_values(array_diff($this->contractRoutes(), $actual));

        $this->assertSame([], $missing, "以下路由缺失：\n" . implode("\n", $missing));
    }

    public function test_no_unexpected_business_routes(): void
    {
        $extra = array_values(array_diff(
            $this->actualRoutes(),
            $this->contractRoutes(),
            self::FRAMEWORK_ROUTES,
            self::V2_ADDITIONS
        ));

        $this->assertSame([], $extra, "多出未登记的路由：\n" . implode("\n", $extra));
    }

    public function test_route_count_matches_the_contract(): void
    {
        $this->assertCount(
            count($this->contractRoutes()),
            $this->actualRoutes(),
            '路由总数与契约基准不一致'
        );
    }

    /**
     * 新增 API 面的正向契约。
     *
     * 路由表补齐了账号、相册、令牌、订单、工单、反馈、举报、日志、
     * 存储、分组、驱动、社交登录、统计、管理端等接口，目标是 API 数量达到 100+，
     * 缩小与兰空 Pro 的差距（AI 与主题功能按需求暂未加）。这条测试锁死「数量下限 +
     * 关键新增端点必须存在」，避免有人误删接口。
     */
    public function test_api_v2_surface_covers_the_expanded_endpoints(): void
    {
        $api = array_values(array_filter(
            $this->actualRoutes(),
            static fn (string $r): bool => str_contains($r, ' /api/v1/')
        ));

        $this->assertGreaterThanOrEqual(
            100,
            count($api),
            'API 接口数量应达到 100+（补齐与兰空 Pro 的差距）'
        );

        $must = [
            'GET /api/v1/me',
            'GET /api/v1/me/quota',
            'POST /api/v1/me/password',
            'GET /api/v1/images/trash',
            'POST /api/v1/images/batch-delete',
            'GET /api/v1/images/search',
            'GET /api/v1/images/random',
            'GET /api/v1/images/stat',
            'GET /api/v1/albums/share/{key}',
            'POST /api/v1/tokens/revoke-all',
            'GET /api/v1/packages/active',
            'POST /api/v1/coupon/check',
            'GET /api/v1/orders/stat',
            'GET /api/v1/notices/{id:num}',
            'GET /api/v1/tickets/{id:num}/replies',
            'GET /api/v1/feedback',
            'GET /api/v1/reports',
            'GET /api/v1/logs',
            'GET /api/v1/storages/default',
            'GET /api/v1/groups',
            'GET /api/v1/drivers/{id:num}',
            'GET /api/v1/socials',
            'GET /api/v1/site/config',
            'GET /api/v1/health',
            'GET /api/v1/stats/images',
            'GET /api/v1/admin/stats',
        ];

        $missing = array_values(array_diff($must, $api));
        $this->assertSame([], $missing, "以下新增 API 端点缺失：\n" . implode("\n", $missing));
    }

    public function test_api_routes_are_all_under_the_v1_prefix(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/')) {
                $this->assertStringStartsWith(
                    'api/v1/',
                    $route->uri(),
                    'API 路由必须带 /api/v1/ 前缀'
                );
            }
        }
    }

    public function test_api_routes_do_not_require_csrf_token(): void
    {
        // API 走 Token 鉴权，CSRF 豁免名单里必须有 api/*
        $this->assertContains('api/*', $this->csrfExceptions());
    }

    public function test_install_route_is_csrf_exempt(): void
    {
        // 安装向导在装站之前运行，那时还没有会话，不需要 CSRF
        $this->assertContains('install', $this->csrfExceptions());
    }

    public function test_payment_notify_is_csrf_exempt(): void
    {
        // 支付回调是第三方服务器发起的，没有用户会话，不能走 CSRF
        $this->assertContains('pay/notify/*', $this->csrfExceptions());
    }

    public function test_session_cookie_is_not_encrypted(): void
    {
        // 会话 Cookie 必须保持明文，否则老会话读不出来
        $this->assertContains('woniu_session', $this->encryptedCookieExceptions());
    }

    public function test_session_cookie_name_matches_contract(): void
    {
        $this->assertSame('woniu_session', config('session.cookie'));
    }

    public function test_session_user_key_matches_contract(): void
    {
        // 会话里存用户 id 的键名固定为 user_id，老会话才能直接接管
        $this->assertSame('user_id', \Illuminate\Support\Facades\Auth::guard('web')->getName());
    }

    /**
     * 从中间件里取出 CSRF 豁免名单。
     *
     * Laravel 13 用静态属性保存豁免名单（PreventRequestForgery::$neverVerify），
     * 由 Middleware::validateCsrfTokens() 通过 except() 写入。
     */
    private function csrfExceptions(): array
    {
        $value = $this->readStaticProperty(
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
            'neverVerify'
        );
        return is_array($value) ? $value : [];
    }

    /**
     * 从 EncryptCookies 中间件里取出不加密的 Cookie 名单。
     *
     * 自定义的 App\Http\Middleware\EncryptCookies 用实例属性 $except 声明豁免，
     * 框架父类则用静态属性 $neverEncrypt。两边都读一遍。
     */
    private function encryptedCookieExceptions(): array
    {
        $result = $this->readStaticProperty(
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            'neverEncrypt'
        );
        if (!is_array($result)) {
            $result = [];
        }

        // 自定义子类用实例属性
        $instance = app(\App\Http\Middleware\EncryptCookies::class);
        $ref = new \ReflectionClass($instance);
        if ($ref->hasProperty('except')) {
            $prop = $ref->getProperty('except');
            $prop->setAccessible(true);
            $value = $prop->getValue($instance);
            if (is_array($value)) {
                $result = array_merge($result, $value);
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * 读类的静态属性（protected / private 都行）。
     */
    private function readStaticProperty(string $class, string $name): mixed
    {
        if (!class_exists($class)) {
            return null;
        }
        $ref = new \ReflectionClass($class);
        if (!$ref->hasProperty($name)) {
            return null;
        }
        $prop = $ref->getProperty($name);
        $prop->setAccessible(true);
        return $prop->getValue();
    }
}
