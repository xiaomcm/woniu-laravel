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

class Github extends SocialDriver
{
    public function name(): string
    {
        return 'GitHub';
    }

    public function authorizeUrl(string $state, string $redirect): string
    {
        return 'https://github.com/login/oauth/authorize?' . http_build_query([
            'client_id'    => (string) $this->cfg('client_id'),
            'redirect_uri' => $redirect,
            'scope'        => 'read:user user:email',
            'state'        => $state,
        ]);
    }

    public function fetchUser(string $code, string $redirect): array
    {
        $tokenResp = Http::request('POST', 'https://github.com/login/oauth/access_token', [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ], json_encode([
            'client_id'     => (string) $this->cfg('client_id'),
            'client_secret' => (string) $this->cfg('client_secret'),
            'code'          => $code,
            'redirect_uri'  => $redirect,
        ]));

        $tokenJson = json_decode($tokenResp['body'], true);
        $token = is_array($tokenJson) ? (string) ($tokenJson['access_token'] ?? '') : '';
        if ($token === '') {
            throw new \RuntimeException('GitHub 换取令牌失败：' . mb_substr($tokenResp['body'], 0, 160));
        }

        $userResp = Http::request('GET', 'https://api.github.com/user', [
            'Accept'        => 'application/vnd.github+json',
            'Authorization' => 'Bearer ' . $token,
            'User-Agent'    => 'Woniu-Image-Host',
        ]);
        $user = json_decode($userResp['body'], true);
        if (!is_array($user) || empty($user['id'])) {
            throw new \RuntimeException('GitHub 获取用户信息失败');
        }

        $email = (string) ($user['email'] ?? '');
        if ($email === '') {
            $mailResp = Http::request('GET', 'https://api.github.com/user/emails', [
                'Accept'        => 'application/vnd.github+json',
                'Authorization' => 'Bearer ' . $token,
                'User-Agent'    => 'Woniu-Image-Host',
            ]);
            $mails = json_decode($mailResp['body'], true);
            if (is_array($mails)) {
                foreach ($mails as $item) {
                    if (!empty($item['primary']) && !empty($item['verified'])) {
                        $email = (string) $item['email'];
                        break;
                    }
                }
            }
        }

        return [
            'openid'   => 'gh_' . (string) $user['id'],
            'nickname' => (string) ($user['name'] ?? $user['login'] ?? 'GitHub 用户'),
            'avatar'   => (string) ($user['avatar_url'] ?? ''),
            'email'    => $email,
            'unionid'  => '',
        ];
    }
}
