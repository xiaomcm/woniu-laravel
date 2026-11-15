<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers\Payment;

use App\Core\Http;
use App\Drivers\PaymentDriver;

/**
 * Stripe Checkout（v1/checkout/sessions）。
 * 支付：创建一次性 Checkout Session，返回会话 URL 跳转。
 * 回调：用 webhook signing secret 校验 Stripe-Signature（HMAC-SHA256）。
 */
class Stripe extends PaymentDriver
{
    public function name(): string
    {
        return 'Stripe';
    }

    private function apiKey(): string
    {
        return (string) $this->cfg('secret_key');
    }

    private function currency(): string
    {
        // Stripe 货币用小写，且大多以「最小货币单位」计（如 usd 用分）
        return strtolower((string) $this->cfg('currency', 'usd'));
    }

    public function pay(array $order): array
    {
        $return = (string) ($order['return_url'] ?? '');
        $fields = [
            'mode'               => 'payment',
            'success_url'        => $return,
            'cancel_url'         => $return,
            'client_reference_id' => (string) $order['out_trade_no'],
            'line_items[0][price_data][currency]'     => $this->currency(),
            'line_items[0][price_data][product_data][name]' => (string) $order['subject'],
            'line_items[0][price_data][unit_amount]'  => (int) $order['amount_cents'],
            'line_items[0][quantity]'                  => '1',
            'metadata[out_trade_no]'                   => (string) $order['out_trade_no'],
        ];

        $resp = Http::request(
            'POST',
            'https://api.stripe.com/v1/checkout/sessions',
            [
                'Authorization' => 'Bearer ' . $this->apiKey(),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            http_build_query($fields),
            30
        );

        if ($resp['error'] !== '' || $resp['status'] < 200 || $resp['status'] >= 300) {
            throw new \RuntimeException('Stripe 创建会话失败：' . ($resp['error'] !== '' ? $resp['error'] : $resp['body']));
        }

        $data = json_decode($resp['body'], true);
        if (!is_array($data) || empty($data['url'])) {
            throw new \RuntimeException('Stripe 响应缺少会话 URL');
        }

        return [
            'mode'    => 'redirect',
            'url'     => (string) $data['url'],
            'qr'      => '',
            'payload' => $data,
        ];
    }

    public function verifyNotify(array $params, array $headers = [], string $body = ''): ?array
    {
        $sig = (string) ($headers['stripe-signature'] ?? '');
        $secret = (string) $this->cfg('webhook_secret');
        if ($sig === '' || $secret === '' || $body === '') {
            // 非 webhook 形态（如直接传解析后的数组）则尝试从 event 读取
            $event = $params;
        } else {
            if (!$this->verifyStripeSig($sig, $body, $secret)) {
                return null;
            }
            $event = json_decode($body, true);
        }

        if (!is_array($event)) {
            return null;
        }
        if ((string) ($event['type'] ?? '') !== 'checkout.session.completed') {
            return null;
        }

        $obj = $event['data']['object'] ?? [];
        $outTradeNo = (string) ($obj['metadata']['out_trade_no'] ?? $obj['client_reference_id'] ?? '');
        if ($outTradeNo === '') {
            return null;
        }

        return [
            'out_trade_no' => $outTradeNo,
            'trade_no'     => (string) ($obj['id'] ?? $obj['payment_intent'] ?? ''),
            'amount_cents' => (int) ($obj['amount_total'] ?? 0),
            'paid'         => true,
        ];
    }

    public function notifyResponse(): string
    {
        return '{"received":true}';
    }

    private function verifyStripeSig(string $sigHeader, string $body, string $secret): bool
    {
        $parts = [];
        foreach (explode(',', $sigHeader) as $pair) {
            $kv = explode('=', $pair, 2);
            if (count($kv) === 2) {
                $parts[$kv[0]] = $kv[1];
            }
        }
        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';
        if ($timestamp === '' || $signature === '') {
            return false;
        }
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }
        $signed = hash_hmac('sha256', $timestamp . '.' . $body, $secret, false);
        return is_string($signed) && hash_equals($signed, $signature);
    }
}
