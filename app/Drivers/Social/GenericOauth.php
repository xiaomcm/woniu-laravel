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

class GenericOauth extends SocialDriver
{
    public function name(): string
    {
        return '通用 OAuth2';
    }

    public function authorizeUrl(string $state, string $redirect): string
    {
        $base = (string) $this->cfg('authorize_url');
        $sep = strpos($base, '?') === false ? '?' : '&';
        return $base . $sep . http_build_query([
            'response_type' => 'code',
            'client_id'     => (string) $this->cfg('client_id'),
            'redirect_uri'  => $redirect,
            'scope'         => (string) $this->cfg('scope', 'user:email'),
            'state'         => $state,
        ]);
    }

    public function fetchUser(string $code, string $redirect): array
    {
        $tokenResp = Http::request('POST', (string) $this->cfg('token_url'), [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], http_build_query([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $redirect,
            'client_id'     => (string) $this->cfg('client_id'),
            'client_secret' => (string) $this->cfg('client_secret'),
        ]));

        $tokenJson = json_decode($tokenResp['body'], true);
        $token = '';
        if (is_array($tokenJson)) {
            $token = (string) ($tokenJson['access_token'] ?? '');
        }
        if ($token === '' && $tokenResp['body'] !== '') {
            parse_str($tokenResp['body'], $parsed);
            $token = (string) ($parsed['access_token'] ?? '');
        }
        if ($token === '') {
            throw new \RuntimeException('OAuth2 换取令牌失败：' . mb_substr($tokenResp['body'], 0, 160));
        }

        $userResp = Http::request('GET', (string) $this->cfg('userinfo_url'), [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'User-Agent'    => 'Woniu-Image-Host',
        ]);
        $user = json_decode($userResp['body'], true);
        if (!is_array($user)) {
            throw new \RuntimeException('OAuth2 用户信息解析失败');
        }

        $idField = (string) $this->cfg('id_field', 'id');
        $nameField = (string) $this->cfg('name_field', 'name');
        $avatarField = (string) $this->cfg('avatar_field', 'avatar');
        $emailField = (string) $this->cfg('email_field', 'email');

        $rawId = (string) ($user[$idField] ?? '');
        if ($rawId === '') {
            throw new \RuntimeException('OAuth2 响应缺少用户标识字段 ' . $idField);
        }

        return [
            'openid'   => 'oa_' . $rawId,
            'nickname' => (string) ($user[$nameField] ?? 'OAuth 用户'),
            'avatar'   => (string) ($user[$avatarField] ?? ''),
            'email'    => (string) ($user[$emailField] ?? ''),
            'unionid'  => '',
        ];
    }
}
