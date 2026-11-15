<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Setting;
use App\Services\CaptchaService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CaptchaController extends Controller
{
    /** 本地图形验证码出图（每次请求都会刷新答案，点图片即可换一张） */
    public function image(): void
    {
        $svg = CaptchaService::image();
        if ($svg === null) {
            // 未启用或不是图形模式时给一个空占位，避免浏览器显示破图
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>';
        }

        throw new HttpResponseException(new SymfonyResponse($svg, 200, [
            'Content-Type'   => 'image/svg+xml; charset=utf-8',
            'Cache-Control'  => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'         => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]));
    }

    /** 云厂商短信验证码下发（注册页「获取验证码」按钮） */
    public function sendSms(): void
    {
        $this->validateCsrf();

        if (!Setting::bool('register_enabled', true)) {
            $this->api(false, '当前站点已关闭注册', null, 403);
        }

        $phone = trim((string) Request::input('phone', ''));
        $result = CaptchaService::sendSms($phone);
        $this->api($result->pass, $result->message, null, $result->pass ? 200 : 429);
    }
}
