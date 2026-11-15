<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers;

abstract class SocialDriver extends Driver
{
    public function type(): string
    {
        return 'social';
    }

    abstract public function authorizeUrl(string $state, string $redirect): string;

    /**
     * @return array{openid:string,nickname:string,avatar:string,email:string,unionid:string}
     */
    abstract public function fetchUser(string $code, string $redirect): array;
}
