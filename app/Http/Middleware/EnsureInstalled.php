<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * 未安装时把请求引到安装向导。
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next)
    {
        if (self::installed()) {
            return $next($request);
        }
        if ($request->is('install')) {
            return $next($request);
        }
        return redirect('/install');
    }

    /**
     * 是否已完成安装：锁定文件或安装配置文件存在即视为已安装。
     */
    public static function installed(): bool
    {
        // 测试基座会自己重建 SQLite 测试库并建表，干净 clone 里没有安装标记，
        // 但仍然不该被引到安装向导（否则所有 HTTP 冒烟用例都拿到 302）。
        if (env('APP_ENV') === 'testing') {
            return true;
        }

        return is_file(BASE_PATH . '/install.lock')
            || is_file(BASE_PATH . '/config/installed.php');
    }
}
