<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Auth;

use Illuminate\Auth\SessionGuard;

/**
 * 会话守卫。
 *
 * 会话里的登录键名使用 user_id，而不是 Laravel 默认的 login_web_<hash>。
 * 这样会话文件保持明文可读，站点迁移后已有会话仍可直接续用。
 */
class WoniuGuard extends SessionGuard
{
    public function getName()
    {
        return 'user_id';
    }
}
