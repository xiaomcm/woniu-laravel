<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers;

final class Result
{
    public function __construct(
        public bool $pass,
        public string $message = '',
        public array $raw = []
    ) {
    }

    public static function pass(string $message = '', array $raw = []): self
    {
        return new self(true, $message, $raw);
    }

    public static function fail(string $message, array $raw = []): self
    {
        return new self(false, $message, $raw);
    }
}
