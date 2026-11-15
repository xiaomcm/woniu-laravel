<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

namespace App\Http\Middleware;

use App\Core\Setting;
use Closure;
use Illuminate\Http\Request;

/**
 * 会话回收周期。
 *
 * 解锁状态（分享密码）存在会话里，会话文件活不过密码有效期的话，
 * 用户会莫名其妙被踢回密码页。php.ini 默认的 gc_maxlifetime 只有 1440 秒，必须顶上去。
 */
class SessionGc
{
    public function handle(Request $request, Closure $next)
    {
        $sessionLifetime = (int) config('session.lifetime', 1440) * 60;

        $shareTtl = 7200;
        if (is_file(BASE_PATH . '/config/installed.php')) {
            $shareTtl = max(60, (int) Setting::get('share_password_ttl', '7200'));
        }

        ini_set('session.gc_maxlifetime', (string) max($sessionLifetime, $shareTtl + 3600, 86400));

        return $next($request);
    }
}
