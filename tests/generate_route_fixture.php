<?php
/**
 * 路由契约基准生成器。
 *
 * 用与 tests/Feature/RouteContractTest::normalize() 完全一致的逻辑，
 * 把当前 Laravel 路由表导出成 `METHOD /path` 清单，写入 Fixtures/routes-2.0.txt。
 *
 * 路由表包含全部 API 接口（账号、相册、令牌、订单、工单、反馈、举报、日志、
 * 存储、分组、驱动、社交登录、统计、管理端）。本脚本保证基准与实际一致，
 * 改动路由后先重新生成基准再提交。
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Route;

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

$normalize = static function (string $uri, array $wheres) use ($type): string {
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
};

$routes = [];
foreach (Route::getRoutes() as $route) {
    $uri = '/' . ltrim($route->uri(), '/');
    if ($uri === '//') {
        $uri = '/';
    }
    $pattern = $normalize($uri, $route->wheres);
    foreach ($route->methods() as $method) {
        if ($method === 'OPTIONS' || $method === 'HEAD') {
            continue;
        }
        $routes[] = $method . ' ' . $pattern;
    }
}
$routes = array_values(array_unique($routes));
sort($routes);

$content = "# 蜗牛图床路由契约基准\n"
    . "# 由 tests/generate_route_fixture.php 从当前路由表导出，格式：METHOD /path\n"
    . "# 这是必须注册的全部路由，一条不多一条不少。\n"
    . "# 改这份清单前先想清楚是不是漏了功能。\n"
    . "#\n"
    . "# 共 " . count($routes) . " 条\n\n"
    . implode("\n", $routes) . "\n";

$target = __DIR__ . '/Fixtures/routes-2.0.txt';
file_put_contents($target, $content);
echo "wrote " . count($routes) . " routes to " . $target . "\n";
