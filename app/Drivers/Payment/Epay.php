<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers\Payment;

use App\Drivers\PaymentDriver;

class Epay extends PaymentDriver
{
    public function name(): string
    {
        return '彩虹易支付';
    }

    public function pay(array $order): array
    {
        $params = [
            'pid'          => (string) $this->cfg('pid'),
            'type'         => (string) $this->cfg('pay_type', 'alipay'),
            'out_trade_no' => (string) $order['out_trade_no'],
            'notify_url'   => (string) ($order['notify_url'] ?? ''),
            'return_url'   => (string) ($order['return_url'] ?? ''),
            'name'         => mb_substr((string) $order['subject'], 0, 60),
            'money'        => number_format(((int) $order['amount_cents']) / 100, 2, '.', ''),
        ];
        $params['sign'] = $this->sign($params);
        $params['sign_type'] = 'MD5';

        $base = rtrim((string) $this->cfg('api_url'), '/');

        return [
            'mode'    => 'redirect',
            'url'     => $base . '/submit.php?' . http_build_query($params),
            'qr'      => '',
            'payload' => $params,
        ];
    }

    public function verifyNotify(array $params, array $headers = [], string $body = ''): ?array
    {
        $sign = (string) ($params['sign'] ?? '');
        if ($sign === '' || strcasecmp($sign, $this->sign($params)) !== 0) {
            return null;
        }
        if ((string) ($params['trade_status'] ?? '') !== 'TRADE_SUCCESS') {
            return null;
        }

        return [
            'out_trade_no' => (string) ($params['out_trade_no'] ?? ''),
            'trade_no'     => (string) ($params['trade_no'] ?? ''),
            'amount_cents' => (int) round(((float) ($params['money'] ?? 0)) * 100),
            'paid'         => true,
        ];
    }

    public function notifyResponse(): string
    {
        return 'success';
    }

    private function sign(array $params): string
    {
        unset($params['sign'], $params['sign_type']);
        $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null && $v !== 0 && $v !== '0');
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }
        return md5(implode('&', $pairs) . (string) $this->cfg('key'));
    }
}
