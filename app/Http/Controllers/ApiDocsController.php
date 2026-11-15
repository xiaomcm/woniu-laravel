<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Setting;
use Illuminate\Http\Response as HttpResponse;

/**
 * API 用法说明页。
 *
 * 文档内容写在视图里（接口清单、认证方式、示例）；这里只负责备好
 * 站点基地址、当前登录用户的令牌状态、以及两个开关的实时状态，
 * 让页面能直接提示「API 已被站主关闭」或「你的账号已关闭 API」。
 */
class ApiDocsController extends Controller
{
    public function index(): HttpResponse
    {
        $me = Auth::user();
        $userApiOn = true;
        if ($me !== null && array_key_exists('api_enabled', $me)) {
            $userApiOn = (int) $me['api_enabled'] === 1;
        }

        return $this->view('api-docs', [
            'pageTitle'    => 'API 文档',
            'apiBase'      => url('api/v1'),
            'siteOn'       => Setting::bool('api_enabled', true),
            'userApiOn'    => $userApiOn,
            'loggedIn'     => $me !== null,
            'user'         => $me,
        ], 'layouts/site');
    }
}
