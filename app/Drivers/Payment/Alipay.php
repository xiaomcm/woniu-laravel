<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers\Payment;

use App\Drivers\PaymentDriver;

class Alipay extends PaymentDriver
{
    public function name(): string
    {
        return '支付宝';
    }

    public function pay(array $order): array
    {
        $bizContent = json_encode([
            'out_trade_no' => (string) $order['out_trade_no'],
            'total_amount' => number_format(((int) $order['amount_cents']) / 100, 2, '.', ''),
            'subject'      => (string) $order['subject'],
            'product_code' => 'FAST_INSTANT_TRADE_PAY',
            'timeout_express' => '30m',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $params = [
            'app_id'      => (string) $this->cfg('app_id'),
            'method'      => 'alipay.trade.page.pay',
            'format'      => 'JSON',
            'charset'     => 'utf-8',
            'sign_type'   => 'RSA2',
            'timestamp'   => date('Y-m-d H:i:s'),
            'version'     => '1.0',
            'notify_url'  => (string) ($order['notify_url'] ?? $this->cfg('notify_url', '')),
            'return_url'  => (string) ($order['return_url'] ?? ''),
            'biz_content' => $bizContent,
        ];

        if ($params['notify_url'] === '') {
            unset($params['notify_url']);
        }
        if ($params['return_url'] === '') {
            unset($params['return_url']);
        }

        $params['sign'] = $this->sign($this->buildSignSource($params));

        return [
            'mode'    => 'redirect',
            'url'     => (string) $this->cfg('gateway', 'https://openapi.alipay.com/gateway.do') . '?' . http_build_query($params),
            'qr'      => '',
            'payload' => $params,
        ];
    }

    public function verifyNotify(array $params, array $headers = [], string $body = ''): ?array
    {
        $sign = (string) ($params['sign'] ?? '');
        if ($sign === '') {
            return null;
        }
        $source = $this->buildSignSource($params);
        if (!$this->verify($source, $sign)) {
            return null;
        }
        if (!in_array((string) ($params['trade_status'] ?? ''), ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)) {
            return null;
        }

        return [
            'out_trade_no' => (string) ($params['out_trade_no'] ?? ''),
            'trade_no'     => (string) ($params['trade_no'] ?? ''),
            'amount_cents' => (int) round(((float) ($params['total_amount'] ?? 0)) * 100),
            'paid'         => true,
        ];
    }

    public function notifyResponse(): string
    {
        return 'success';
    }

    private function buildSignSource(array $params): string
    {
        unset($params['sign']);
        $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null);
        ksort($params);
        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }
        return implode('&', $pairs);
    }

    private function sign(string $source): string
    {
        $key = $this->pemKey((string) $this->cfg('private_key'), false);
        if ($key === '') {
            return '';
        }
        $signature = '';
        openssl_sign($source, $signature, $key, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    private function verify(string $source, string $sign): bool
    {
        $key = $this->pemKey((string) $this->cfg('public_key'), true);
        if ($key === '') {
            return false;
        }
        $raw = base64_decode($sign, true);
        if ($raw === false) {
            return false;
        }
        return openssl_verify($source, $raw, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    private function pemKey(string $raw, bool $public): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (strpos($raw, '-----BEGIN') !== false) {
            return $raw;
        }
        $body = chunk_split(preg_replace('/\s+/', '', $raw) ?? '', 64, "\n");
        $head = $public ? 'PUBLIC KEY' : 'PRIVATE KEY';
        return '-----BEGIN ' . $head . "-----\n" . $body . '-----END ' . $head . "-----\n";
    }
}
