<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers;

abstract class MailDriver extends Driver
{
    public function type(): string
    {
        return 'mail';
    }

    abstract public function send(string $to, string $subject, string $html): Result;
}
