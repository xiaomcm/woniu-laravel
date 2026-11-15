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

class Qq extends SocialDriver
{
    public function name(): string
    {
        return 'QQ 互联';
    }

    public function authorizeUrl(string $state, string $redirect): string
    {
        return 'https://graph.qq.com/oauth2.0/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => (string) $this->cfg('app_id'),
            'redirect_uri'  => $redirect,
            'state'         => $state,
            'scope'         => 'get_user_info',
        ]);
    }

    public function fetchUser(string $code, string $redirect): array
    {
        $tokenResp = Http::request('GET', 'https://graph.qq.com/oauth2.0/token?' . http_build_query([
            'grant_type'    => 'authorization_code',
            'client_id'     => (string) $this->cfg('app_id'),
            'client_secret' => (string) $this->cfg('app_key'),
            'code'          => $code,
            'redirect_uri'  => $redirect,
            'fmt'           => 'json',
        ]));

        $tokenJson = json_decode($tokenResp['body'], true);
        $token = is_array($tokenJson) ? (string) ($tokenJson['access_token'] ?? '') : '';
        if ($token === '') {
            throw new \RuntimeException('QQ 换取令牌失败：' . mb_substr($tokenResp['body'], 0, 160));
        }

        $meResp = Http::request('GET', 'https://graph.qq.com/oauth2.0/me?' . http_build_query([
            'access_token' => $token,
            'fmt'          => 'json',
        ]));
        $meJson = json_decode($meResp['body'], true);
        $openid = is_array($meJson) ? (string) ($meJson['openid'] ?? '') : '';
        if ($openid === '') {
            throw new \RuntimeException('QQ 获取 OpenID 失败');
        }

        $infoResp = Http::request('GET', 'https://graph.qq.com/user/get_user_info?' . http_build_query([
            'access_token'         => $token,
            'oauth_consumer_key'   => (string) $this->cfg('app_id'),
            'openid'               => $openid,
        ]));
        $info = json_decode($infoResp['body'], true);
        if (!is_array($info) || (int) ($info['ret'] ?? -1) !== 0) {
            throw new \RuntimeException('QQ 获取用户信息失败：' . (string) ($info['msg'] ?? ''));
        }

        return [
            'openid'   => 'qq_' . $openid,
            'nickname' => (string) ($info['nickname'] ?? 'QQ 用户'),
            'avatar'   => (string) ($info['figureurl_qq_2'] ?? $info['figureurl_qq_1'] ?? ''),
            'email'    => '',
            'unionid'  => '',
        ];
    }
}
