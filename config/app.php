<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 *
 * Laravel 应用配置。
 * 应用名 / 密钥 / 地址 / 时区 全部来自 config/installed.php（安装向导生成的），
 * .env 只作为未安装状态下的兜底。
 */

declare(strict_types=1);

$woniu = require dirname(__DIR__) . '/bootstrap/woniu.php';
$app = $woniu['app'] ?? [];

return [

    'name' => (string) ($app['name'] ?? '蜗牛图床'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) ($app['debug'] ?? env('APP_DEBUG', false)),

    'url' => (string) ($app['url'] ?? env('APP_URL', 'http://localhost')),

    'timezone' => (string) ($app['timezone'] ?? 'Asia/Shanghai'),

    'locale' => (string) ($app['locale'] ?? 'zh_CN'),

    'fallback_locale' => (string) ($app['locale'] ?? 'zh_CN'),

    'faker_locale' => 'zh_CN',

    'cipher' => 'AES-256-CBC',

    // 原有的 app.key 是 32 位定长字符串，Laravel 的 AES-256-CBC 直接可用，
    // 沿用同一个 key，老会话与已加密数据才能继续解开。
    // 未安装时 installed.php 还不存在，这里回落到 .env 的 APP_KEY；
    // 再没有的话由 AppServiceProvider 补一个临时密钥，保证安装向导能跑起来。
    'key' => (string) ($app['key'] ?: env('APP_KEY', '')),

    'previous_keys' => [
        ...array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', ''))),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    //既有的版本号，页面里的资源版本号拼接要用
    'version' => (string) ($app['version'] ?? '2.0.0'),

];
