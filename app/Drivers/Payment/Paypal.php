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
 * PayPal Checkout（v2/orders）。
 * 支付：获取 access_token → 创建 order → 返回 approve 链接跳转。
 * 回调：验证 PAYPAL 官方 Webhook 签名（证书 + HMAC），再解析事件。
 */
class Paypal extends PaymentDriver
{
    public function name(): string
    {
        return 'PayPal';
    }

    private function baseUrl(): string
    {
        // 1 为沙箱，0 为正式环境
        return $this->bool('sandbox', false)
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    private function basicAuth(): string
    {
        return 'Basic ' . base64_encode((string) $this->cfg('client_id') . ':' . (string) $this->cfg('client_secret'));
    }

    public function pay(array $order): array
    {
        $token = $this->fetchToken();
        if ($token === '') {
            throw new \RuntimeException('PayPal 获取 access_token 失败，请检查 client_id/secret 与网络');
        }

        $amount = number_format(((int) $order['amount_cents']) / 100, 2, '.', '');
        $currency = strtoupper((string) $this->cfg('currency', 'USD'));

        $body = json_encode([
            'intent'              => 'CAPTURE',
            'purchase_units'      => [[
                'invoice_id'   => (string) $order['out_trade_no'],
                'description'  => (string) $order['subject'],
                'amount'       => ['currency_code' => $currency, 'value' => $amount],
            ]],
            'application_context' => [
                'return_url' => (string) ($order['return_url'] ?? ''),
                'cancel_url' => (string) ($order['return_url'] ?? ''),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $resp = Http::request(
            'POST',
            $this->baseUrl() . '/v2/checkout/orders',
            [
                'Authorization'   => 'Bearer ' . $token,
                'Content-Type'    => 'application/json',
                'PayPal-Request-Id' => (string) $order['out_trade_no'],
            ],
            $body,
            30
        );

        if ($resp['error'] !== '' || $resp['status'] < 200 || $resp['status'] >= 300) {
            throw new \RuntimeException('PayPal 创建订单失败：' . ($resp['error'] !== '' ? $resp['error'] : $resp['body']));
        }

        $data = json_decode($resp['body'], true);
        if (!is_array($data)) {
            throw new \RuntimeException('PayPal 返回无法解析');
        }

        $approve = '';
        foreach ((array) ($data['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approve = (string) ($link['href'] ?? '');
                break;
            }
        }
        if ($approve === '') {
            throw new \RuntimeException('PayPal 响应缺少 approve 链接');
        }

        return [
            'mode'    => 'redirect',
            'url'     => $approve,
            'qr'      => '',
            'payload' => $data,
        ];
    }

    public function verifyNotify(array $params, array $headers = [], string $body = ''): ?array
    {
        // PayPal 回调为 Webhook 事件，原始 JSON 在 $body
        if ($body === '') {
            $body = (string) json_encode($params);
        }
        if (!$this->verifyWebhook($headers, $body)) {
            return null;
        }

        $event = json_decode($body, true);
        if (!is_array($event)) {
            return null;
        }

        $type = (string) ($event['event_type'] ?? '');
        if (!in_array($type, ['CHECKOUT.ORDER.COMPLETED', 'PAYMENT.CAPTURE.COMPLETED'], true)) {
            // 非支付成功事件，按已处理返回（避免重复通知报错）
            return null;
        }

        $resource = $event['resource'] ?? [];
        $units = $resource['purchase_units'] ?? [];
        $first = is_array($units) && isset($units[0]) ? $units[0] : [];
        $invoice = (string) ($first['invoice_id'] ?? $resource['invoice_id'] ?? '');
        if ($invoice === '') {
            return null;
        }

        $amountStr = (string) ($first['amount']['value'] ?? $resource['amount']['value'] ?? '0');
        $amountCents = (int) round(((float) $amountStr) * 100);

        return [
            'out_trade_no' => $invoice,
            'trade_no'     => (string) ($resource['id'] ?? $type),
            'amount_cents' => $amountCents,
            'paid'         => true,
        ];
    }

    public function notifyResponse(): string
    {
        return '{"status":"SUCCESS"}';
    }

    private function fetchToken(): string
    {
        $resp = Http::request(
            'POST',
            $this->baseUrl() . '/v1/oauth2/token',
            [
                'Authorization' => $this->basicAuth(),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'grant_type=client_credentials',
            20
        );
        if ($resp['error'] !== '' || $resp['status'] < 200 || $resp['status'] >= 300) {
            return '';
        }
        $data = json_decode($resp['body'], true);
        return is_array($data) ? (string) ($data['access_token'] ?? '') : '';
    }

    private function verifyWebhook(array $headers, string $body): bool
    {
        $algo = strtoupper((string) ($headers['paypal-auth-algo'] ?? ''));
        $certUrl = (string) ($headers['paypal-cert-url'] ?? '');
        $transId = (string) ($headers['paypal-transmission-id'] ?? '');
        $transSig = (string) ($headers['paypal-transmission-sig'] ?? '');
        $transTime = (string) ($headers['paypal-transmission-time'] ?? '');
        $webhookId = (string) $this->cfg('webhook_id');

        if ($algo === '' || $certUrl === '' || $transId === '' || $transSig === '' || $transTime === '' || $webhookId === '') {
            return false;
        }

        // 时间窗口：±10 分钟
        if (abs(time() - (int) $transTime) > 600) {
            return false;
        }

        // 证书必须来自 paypal 域名，避免伪造
        $host = parse_url($certUrl, PHP_URL_HOST);
        if (!is_string($host) || !preg_match('/\.paypal\.(com|cn)$/i', $host)) {
            return false;
        }

        $certResp = Http::request('GET', $certUrl, [], null, 15);
        if ($certResp['error'] !== '' || $certResp['status'] < 200 || $certResp['status'] >= 300) {
            return false;
        }
        $cert = $certResp['body'];

        $crc = (string) crc32($body);
        $expected = $transId . ':' . $transTime . ':' . $webhookId . ':' . $crc;

        $alg = $algo === 'SHA256WITHRSA' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;
        $raw = base64_decode($transSig, true);
        if ($raw === false) {
            return false;
        }
        return openssl_verify($expected, $raw, $cert, $alg) === 1;
    }
}
