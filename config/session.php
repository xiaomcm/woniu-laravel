<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 *
 * 会话配置。刻意与既有行为保持一致：
 * - cookie 名沿用 woniu_session
 * - 会话文件目录沿用 storage/sessions（既有的老会话文件直接续用）
 * - 序列化方式沿用 php（原先使用的是 PHP 原生序列化），老会话内容能原样读出来
 * 这样站点迁移之后，已有用户不需要重新登录。
 */

declare(strict_types=1);

use Illuminate\Support\Str;

$woniu = require dirname(__DIR__) . '/bootstrap/woniu.php';
$session = $woniu['session'] ?? [];

return [

    'driver' => env('SESSION_DRIVER', 'file'),

    // Laravel 的 lifetime 单位是分钟，原先是秒
    'lifetime' => (int) round(((int) ($session['lifetime'] ?? 86400)) / 60),

    'expire_on_close' => false,

    'encrypt' => false,

    'files' => storage_path('sessions'),

    'connection' => env('SESSION_CONNECTION'),

    'table' => env('SESSION_TABLE', 'sessions'),

    'store' => env('SESSION_STORE'),

    'lottery' => [2, 100],

    'cookie' => (string) ($session['name'] ?? env('SESSION_COOKIE', 'woniu_session')),

    'path' => env('SESSION_PATH', '/'),

    'domain' => env('SESSION_DOMAIN'),

    'secure' => env('SESSION_SECURE_COOKIE'),

    'http_only' => true,

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    'partitioned' => false,

    'serialization' => 'php',

];
