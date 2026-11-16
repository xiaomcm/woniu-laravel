<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Core;

use App\Models\User;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth as LaravelAuth;

/**
 * 登录态。
 *
 * 底层用 Laravel 的会话守卫（App\Auth\WoniuGuard），会话里的键名沿用既有的
 * user_id，所以站点迁移后已有会话仍可续用。
 *
 * 对外仍然返回数组（既有的契约），业务代码里的 $user['id'] 一个字都不用改。
 */
class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function guard(): Guard
    {
        return LaravelAuth::guard('web');
    }

    /**
     * 清空进程内的登录态缓存。
     *
     * 登录态是「一次读入、全进程复用」的设计。单元测试里换会话或登出后要调这个，
     * 否则会读到上一轮的旧用户。
     */
    public static function reset(): void
    {
        self::$user = null;
        self::$loaded = false;
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;

        $id = (int) Session::get('user_id', 0);
        if ($id <= 0) {
            return null;
        }

        // 账号自动退出：无操作时长超过后台设定（秒，0 为不限制）就地登出
        $ttl = Setting::int('session_ttl', 0);
        if ($ttl > 0) {
            $last = (int) Session::get('_last_active', 0);
            if ($last > 0 && time() - $last > $ttl) {
                self::logout();
                return null;
            }
        }

        $model = self::guard()->user();
        if ($model !== null && (int) $model->status === 1) {
            self::$user = self::toArray($model);
            // 套餐到期即时降级：用户下次进站就把过期套餐收回默认用户组。
            // duration_days = 0 的永久套餐到期时间是 NULL，不受影响。
            self::$user = self::expirePackageIfNeeded(self::$user);
            // 每个请求都算活跃，续上倒计时
            Session::put('_last_active', time());
        } else {
            Session::forget('user_id');
        }

        return self::$user;
    }

    /**
     * 套餐到期即时降级。
     *
     * 用户的套餐到期后要退回默认用户组、清掉套餐专属容量。
     * duration_days = 0 的永久套餐到期时间是 NULL，这里直接放行。
     *
     * 只动当前这一个用户，不扫全表，所以放在登录态读取路径上开销可以忽略。
     */
    private static function expirePackageIfNeeded(array $user): array
    {
        $expire = (string) ($user['package_expired_at'] ?? '');
        if ($expire === '' || $expire === '0000-00-00 00:00:00') {
            // 没有到期时间 = 永久有效，或者本来就没买套餐
            return $user;
        }

        $expireTs = strtotime($expire);
        if ($expireTs === false || $expireTs >= time()) {
            return $user;
        }

        // 已经过期：退回默认用户组
        $defaultGroup = (int) (Setting::int('default_group_id', 1) ?: 1);
        \App\Models\User::updateById((int) $user['id'], [
            'group_id'           => $defaultGroup,
            'capacity'           => null,
            'package_id'         => null,
            'package_expired_at' => null,
            'group_expired_at'   => null,
        ]);

        $user['group_id']           = $defaultGroup;
        $user['capacity']           = null;
        $user['package_id']         = null;
        $user['package_expired_at'] = null;
        $user['group_expired_at']   = null;

        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user === null ? null : (int) $user['id'];
    }

    public static function attempt(string $account, string $password): ?array
    {
        // 账号可以填邮箱，也可以填用户名
        $user = User::findForLogin($account);
        if ($user === null || !password_verify($password, (string) $user['password'])) {
            return null;
        }
        if ((int) $user['status'] !== 1) {
            return null;
        }
        return $user;
    }

    public static function login(array $user): void
    {
        $model = User::model((int) $user['id']);
        if ($model !== null) {
            // 守卫内部会换 session id，等价于既有的 session_regenerate_id(true)
            self::guard()->login($model);
        }
        Session::put('_last_active', time());
        self::$user = $user;
        self::$loaded = true;
        User::updateById((int) $user['id'], ['last_login_at' => now()]);
    }

    public static function logout(): void
    {
        self::guard()->logout();
        Session::forget('_last_active');
        self::$user = null;
        self::$loaded = true;
    }

    public static function requireLogin(): array
    {
        $user = self::user();
        if ($user === null) {
            if (Request::isAjax()) {
                Response::api(false, '请先登录', null, 401);
            }
            $redirect = urlencode(Request::uri());
            // 直接用 Response::redirect()，不经过全局 redirect() 函数：
            // 全局函数谁先被定义取决于加载顺序（Laravel 与本项目都有 redirect），
            // 而这里的跳转语义必须始终一致。
            Response::redirect('/login?redirect=' . $redirect);
        }
        // Response::redirect() 会抛异常中断请求，正常走不到这里。
        // 万一没中断，绝不能把 null 当成用户数组返回给调用方。
        if (!is_array($user)) {
            throw new \RuntimeException('未登录状态下 requireLogin() 返回了非数组');
        }
        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::requireLogin();
        if ((int) $user['is_admin'] !== 1) {
            abort(403, '需要管理员权限');
        }
        return $user;
    }

    public static function refresh(): void
    {
        self::$loaded = false;
        self::$user = null;
        self::guard()->forgetUser();
        self::$user = self::user();
    }

    /**
     * Eloquent 模型转成既有的数组契约。
     */
    private static function toArray($model): array
    {
        if (is_array($model)) {
            return $model;
        }
        $data = $model->toArray();
        return self::castUser($data);
    }

    private static function castUser(array $row): array
    {
        $row['id']         = (int) $row['id'];
        $row['group_id']   = (int) $row['group_id'];
        $row['is_admin']   = (int) $row['is_admin'];
        $row['status']     = (int) $row['status'];
        $row['capacity']   = $row['capacity'] === null ? null : (int) $row['capacity'];
        return $row;
    }
}
