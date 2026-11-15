<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers;

abstract class CloudDriver extends Driver
{
    public function type(): string
    {
        return 'cloud';
    }

    /**
     * @param array $ops width height quality format blur sharpen filter
     */
    abstract public function buildUrl(string $url, array $ops): string;
}
