<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers;

abstract class SmsDriver extends Driver
{
    public function type(): string
    {
        return 'sms';
    }

    abstract public function send(string $phone, string $code, array $params = []): Result;
}
