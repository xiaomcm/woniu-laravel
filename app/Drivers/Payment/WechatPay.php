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

class WechatPay extends PaymentDriver
{
    private const HOST = 'https://api.mch.weixin.qq.com';

    public function name(): string
    {
        return '微信支付';
    }

    public function pay(array $order): array
    {
        $path = '/v3/pay/transactions/native';
        $body = json_encode([
            'appid'        => (string) $this->cfg('app_id'),
            'mchid'        => (string) $this->cfg('mch_id'),
            'description'  => mb_substr((string) $order['subject'], 0, 120),
            'out_trade_no' => (string) $order['out_trade_no'],
            'notify_url'   => (string) ($order['notify_url'] ?? $this->cfg('notify_url', '')),
            'amount'       => [
                'total'    => (int) $order['amount_cents'],
                'currency' => 'CNY',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = Http::request('POST', self::HOST . $path, [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => $this->authorization('POST', $path, (string) $body),
            'User-Agent'    => 'Woniu-Image-Host',
        ], (string) $body, 15);

        $json = json_decode($response['body'], true);
        $codeUrl = is_array($json) ? (string) ($json['code_url'] ?? '') : '';
        if ($codeUrl === '') {
            throw new \RuntimeException('微信下单失败：' . mb_substr((string) ($json['message'] ?? $response['body']), 0, 200));
        }

        return [
            'mode'    => 'qrcode',
            'url'     => $codeUrl,
            'qr'      => $this->qrImage($codeUrl),
            'payload' => (array) $json,
        ];
    }

    public function verifyNotify(array $params, array $headers = [], string $body = ''): ?array
    {
        $resource = $params['resource'] ?? null;
        if (!is_array($resource)) {
            return null;
        }

        $ciphertext = base64_decode((string) ($resource['ciphertext'] ?? ''), true);
        if ($ciphertext === false || strlen($ciphertext) <= 16) {
            return null;
        }
        $tag = substr($ciphertext, -16);
        $raw = substr($ciphertext, 0, -16);
        $plain = openssl_decrypt(
            $raw,
            'aes-256-gcm',
            (string) $this->cfg('api_v3_key'),
            OPENSSL_RAW_DATA,
            (string) ($resource['nonce'] ?? ''),
            $tag,
            (string) ($resource['associated_data'] ?? '')
        );
        if ($plain === false) {
            return null;
        }

        $data = json_decode($plain, true);
        if (!is_array($data)) {
            return null;
        }
        if ((string) ($data['trade_state'] ?? '') !== 'SUCCESS') {
            return null;
        }

        return [
            'out_trade_no' => (string) ($data['out_trade_no'] ?? ''),
            'trade_no'     => (string) ($data['transaction_id'] ?? ''),
            'amount_cents' => (int) ($data['amount']['total'] ?? 0),
            'paid'         => true,
        ];
    }

    public function notifyResponse(): string
    {
        return json_encode(['code' => 'SUCCESS', 'message' => '成功'], JSON_UNESCAPED_UNICODE);
    }

    private function authorization(string $method, string $path, string $body): string
    {
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $message = $method . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . $body . "\n";

        $key = (string) $this->cfg('private_key');
        if (strpos($key, '-----BEGIN') === false) {
            $key = "-----BEGIN PRIVATE KEY-----\n" . chunk_split(preg_replace('/\s+/', '', $key) ?? '', 64, "\n") . "-----END PRIVATE KEY-----\n";
        }

        $signature = '';
        if (!openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('微信支付签名失败，请检查商户私钥');
        }

        return sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",signature="%s",timestamp="%s",serial_no="%s"',
            (string) $this->cfg('mch_id'),
            $nonce,
            base64_encode($signature),
            $timestamp,
            (string) $this->cfg('cert_serial_no')
        );
    }
}
