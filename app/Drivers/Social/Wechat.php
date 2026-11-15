<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers\Social;

use App\Core\Http;
use App\Drivers\SocialDriver;

class Wechat extends SocialDriver
{
    public function name(): string
    {
        return '微信开放平台';
    }

    public function authorizeUrl(string $state, string $redirect): string
    {
        return 'https://open.weixin.qq.com/connect/qrconnect?' . http_build_query([
            'appid'         => (string) $this->cfg('app_id'),
            'redirect_uri'  => $redirect,
            'response_type' => 'code',
            'scope'         => 'snsapi_login',
            'state'         => $state,
        ]) . '#wechat_redirect';
    }

    public function fetchUser(string $code, string $redirect): array
    {
        $tokenResp = Http::request('GET', 'https://api.weixin.qq.com/sns/oauth2/access_token?' . http_build_query([
            'appid'      => (string) $this->cfg('app_id'),
            'secret'     => (string) $this->cfg('app_secret'),
            'code'       => $code,
            'grant_type' => 'authorization_code',
        ]));
        $tokenJson = json_decode($tokenResp['body'], true);
        if (!is_array($tokenJson) || !empty($tokenJson['errcode'])) {
            throw new \RuntimeException('微信换取令牌失败：' . (string) ($tokenJson['errmsg'] ?? $tokenResp['body']));
        }

        $accessToken = (string) ($tokenJson['access_token'] ?? '');
        $openid = (string) ($tokenJson['openid'] ?? '');

        $infoResp = Http::request('GET', 'https://api.weixin.qq.com/sns/userinfo?' . http_build_query([
            'access_token' => $accessToken,
            'openid'       => $openid,
            'lang'         => 'zh_CN',
        ]));
        $info = json_decode($infoResp['body'], true);
        if (!is_array($info) || !empty($info['errcode'])) {
            throw new \RuntimeException('微信获取用户信息失败：' . (string) ($info['errmsg'] ?? ''));
        }

        return [
            'openid'   => 'wx_' . $openid,
            'nickname' => (string) ($info['nickname'] ?? '微信用户'),
            'avatar'   => (string) ($info['headimgurl'] ?? ''),
            'email'    => '',
            'unionid'  => (string) ($info['unionid'] ?? ''),
        ];
    }
}
