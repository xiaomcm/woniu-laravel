<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Core;

use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * CSRF 令牌。
 *
 * 令牌本身用 Laravel 会话里的那一份（session()->token()），
 * 与框架的 PreventRequestForgery 中间件校验的是同一个值，
 * 所以表单里带什么、校验时比什么，两边天然一致。
 *
 *原先使用的是 $_SESSION['_csrf'] 这个独立键。2.0 换成 Laravel 会话令牌后，
 * 早期发出的旧表单提交上来会校验不过 —— 但用户重新加载页面后即可正常提交，
 * 表单里就是新令牌，不影响使用。
 */
class Csrf
{
    /**
     * 当前会话的 CSRF 令牌。
     */
    public static function token(): string
    {
        return (string) csrf_token();
    }

    /**
     * 校验提交的令牌是否与会话里的一致。
     */
    public static function check(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }
        $current = (string) csrf_token();
        return $current !== '' && hash_equals($current, $token);
    }

    /**
     * POST 请求的 CSRF 校验。
     *
     * 与既有的 enforce() 规则一致：
     * GET / HEAD 放行，/api/ 前缀放行（接口走 Token 鉴权），
     * 其余请求必须带 _token。
     */
    public static function enforce(): void
    {
        if (Request::method() === 'GET' || Request::method() === 'HEAD') {
            return;
        }
        if (str_starts_with(Request::path(), '/api/')) {
            return;
        }
        $token = Request::input('_token');
        if (!self::check(is_string($token) ? $token : null)) {
            if (Request::isAjax()) {
                Response::api(false, '页面已过期，请刷新后重试', null, 419);
            }
            abort(419, '页面已过期，请返回刷新后重试。');
        }
    }

    /**
     * CSRF 隐藏域的 HTML。
     *
     *原先输出的是：
     *   <input type="hidden" name="_token" value="...">
     *
     * Laravel 13 的同名函数多一个 autocomplete="off" 属性，
     * 页面字节会与既有 不一致（表单是逐字节比对的敏感区），
     * 所以由 Blade 编译期把 csrf_field() 换成本方法。
     */
    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }
}
