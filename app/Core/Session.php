<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Core;

use Illuminate\Support\Arr;

/**
 * 会话读写。
 *
 *原先直接读写 $_SESSION 超全局变量，Laravel 不走这个超全局，
 * 所以这里统一收敛到框架的会话存储上，键名与结构保持不变，
 * 原有会话文件因此可以继续用（用户不会被强制登出）。
 *
 * 键名支持点号路径：Session::get('_share.image:5') 等价于 $_SESSION['_share']['image:5']。
 */
class Session
{
    public static function get(string $key, $default = null)
    {
        return session()->get($key, $default);
    }

    public static function put(string $key, $value): void
    {
        session()->put($key, $value);
    }

    public static function has(string $key): bool
    {
        return session()->has($key);
    }

    public static function forget(string $key): void
    {
        session()->forget($key);
    }

    public static function pull(string $key, $default = null)
    {
        return session()->pull($key, $default);
    }

    /**
     * 读取嵌套数组里的一个值，路径用数组给出。
     */
    public static function value(array $path, $default = null)
    {
        return Arr::get(session()->all(), $path, $default);
    }
}
