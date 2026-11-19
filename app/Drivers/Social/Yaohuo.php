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

/**
 * 妖火账号登录（OAuth 2.0）。
 *
 * 按 https://yaohuo.me/OAuth/Docs.html 的「OAuth 2.0 / NewAPI」章节实现：
 * - 授权端点 Authorize.aspx，scope 固定 profile（不要带 openid，那是 OIDC 的路径）
 * - 换令牌 Token.aspx，client_secret 走 form body（client_secret_post）
 * - 用户信息 Profile.aspx，身份字段是 userid（不是 OIDC 的 sub），昵称 nickname
 * - Profile 不返回邮箱，这里留空，由 OauthController 兜底生成 @oauth.local 唯一邮箱
 *
 * 管理员只需在后台填 Client ID / Client Secret，端点和字段映射固定，避免配错 scope 走岔到 OIDC。
 */
class Yaohuo extends SocialDriver
{
    private const AUTHORIZE = 'https://yaohuo.me/OAuth/Authorize.aspx';
    private const TOKEN = 'https://yaohuo.me/OAuth/Token.aspx';
    private const PROFILE = 'https://yaohuo.me/OAuth/Profile.aspx';

    public function name(): string
    {
        return '妖火账号';
    }

    public function authorizeUrl(string $state, string $redirect): string
    {
        return self::AUTHORIZE . '?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => (string) $this->cfg('client_id'),
            'redirect_uri'  => $redirect,
            'scope'         => 'profile',
            'state'         => $state,
        ]);
    }

    public function fetchUser(string $code, string $redirect): array
    {
        // 换令牌：secret 放 form body（妖火采用 client_secret_post）
        $tokenResp = Http::request('POST', self::TOKEN, [
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
        if (!is_array($tokenJson) || isset($tokenJson['error'])) {
            $desc = is_array($tokenJson) ? (string) ($tokenJson['error_description'] ?? $tokenJson['error'] ?? '') : '';
            throw new \RuntimeException('妖火换取令牌失败' . ($desc !== '' ? '：' . mb_substr($desc, 0, 160) : ''));
        }
        $token = (string) ($tokenJson['access_token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException('妖火换取令牌失败：未返回 access_token');
        }

        // 优先按文档建议再调 Profile.aspx；若失败但令牌响应里带了 user_info，用它兜底
        $user = null;
        $profileResp = Http::request('GET', self::PROFILE, [
            'Accept'        => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'User-Agent'    => 'Woniu-Image-Host',
        ]);
        $decoded = json_decode($profileResp['body'], true);
        if (is_array($decoded)) {
            $user = $decoded;
        } elseif (is_array($tokenJson['user_info'] ?? null)) {
            $user = $tokenJson['user_info'];
        }
        if ($user === null || empty($user['userid'])) {
            throw new \RuntimeException('妖火获取用户信息失败');
        }

        return [
            // 加前缀，避免和其它 OAuth 源的纯数字 id 撞号
            'openid'   => 'yh_' . (string) $user['userid'],
            'nickname' => (string) ($user['nickname'] ?? '妖火用户'),
            // Profile 不返回邮箱，留空由调用方兜底生成唯一占位邮箱
            'avatar'   => '',
            'email'    => '',
            'unionid'  => '',
        ];
    }
}
