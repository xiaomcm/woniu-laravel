<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Core;

class HttpException extends \RuntimeException
{
    private int $statusCode;

    public function __construct(int $statusCode, string $message = '')
    {
        parent::__construct($message !== '' ? $message : Response::statusText($statusCode));
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
