<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * 会话 Cookie 保持明文。
     *
     * 会话 ID 以明文保存，站点迁移后已有会话必须还能用，
     * 所以这里把会话 Cookie 排除在加密之外（其余 Cookie 照常加密）。
     *
     * @var array<int, string>
     */
    protected $except = [
        'woniu_session',
    ];
}
