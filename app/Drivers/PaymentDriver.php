<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers;

abstract class PaymentDriver extends Driver
{
    public function type(): string
    {
        return 'payment';
    }

    /**
     * @param array $order out_trade_no subject amount_cents notify_url return_url
     * @return array{mode:string,url:string,qr:string,payload:array}
     */
    abstract public function pay(array $order): array;

    /**
     * 校验异步通知，通过返回订单信息，失败返回 null
     * @return array{out_trade_no:string,trade_no:string,amount_cents:int}|null
     */
    abstract public function verifyNotify(array $params, array $headers = [], string $body = ''): ?array;

    public function notifyResponse(): string
    {
        return 'success';
    }

    public function qrImage(string $content): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=0&data=' . rawurlencode($content);
    }
}
