<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers\Payment;

use App\Drivers\PaymentDriver;

/**
 * 中国银联全渠道网关支付（frontTransReq.do，5.1.0，signMethod=01 RSA）。
 * 支付：组装表单参数 → 用商户私钥做 RSA-SHA256 签名 → 浏览器自动跳转银联页面。
 * 回调：银联后台通知（backUrl）以表单 POST 过来，用银联公钥验签。
 *
 * 配置项：
 *  - mer_id       商户代码
 *  - cert_id      商户证书序列号
 *  - private_key  商户私钥（PEM，不含密码则留空 passphrase）
 *  - key_pass     商户私钥密码（PKCS12 导出为 PEM 时若设了密码）
 *  - unionpay_pub 银联公钥（PEM）
 *  - front_url    前台通知（留空用系统 return）
 *  - test_mode    1=测试环境 0=生产
 */
class Unionpay extends PaymentDriver
{
    public function name(): string
    {
        return '银联';
    }

    public function pay(array $order): array
    {
        $test = $this->bool('test_mode', false);
        $gateway = $test
            ? 'https://gateway.test.95516.com/gateway/api/frontTransReq.do'
            : 'https://gateway.95516.com/gateway/api/frontTransReq.do';

        $now = new \DateTime('now', new \DateTimeZone('Asia/Shanghai'));
        $txnTime = $now->format('YmdHis');

        $params = [
            'version'       => '5.1.0',
            'encoding'      => 'utf-8',
            'certId'        => (string) $this->cfg('cert_id'),
            'signMethod'    => '01',
            'txnType'       => '01',
            'txnSubType'    => '01',
            'bizType'       => '000201',
            'channelType'   => '07',
            'accessType'    => '0',
            'merId'         => (string) $this->cfg('mer_id'),
            'orderId'       => (string) $order['out_trade_no'],
            'txnTime'       => $txnTime,
            'txnAmt'        => (string) ((int) $order['amount_cents']),
            'currencyCode'  => '156',
            'frontUrl'      => (string) ($this->cfg('front_url', (string) ($order['return_url'] ?? ''))),
            'backUrl'       => (string) ($order['notify_url'] ?? ''),
        ];

        $params['signature'] = $this->sign($params);
        if ($params['signature'] === '') {
            throw new \RuntimeException('银联签名失败，请检查 private_key / cert_id 配置');
        }

        return [
            'mode'    => 'form',
            'url'     => $gateway,
            'qr'      => '',
            'payload' => $params,
        ];
    }

    public function verifyNotify(array $params, array $headers = [], string $body = ''): ?array
    {
        if (empty($params)) {
            return null;
        }
        $respCode = (string) ($params['respCode'] ?? '');
        if ($respCode !== '00') {
            return null;
        }
        if (!$this->verifySig($params)) {
            return null;
        }

        $outTradeNo = (string) ($params['orderId'] ?? '');
        $tradeNo = (string) ($params['queryId'] ?? '');
        $amountCents = (int) ($params['txnAmt'] ?? 0);

        if ($outTradeNo === '' || $amountCents <= 0) {
            return null;
        }

        return [
            'out_trade_no' => $outTradeNo,
            'trade_no'     => $tradeNo,
            'amount_cents' => $amountCents,
            'paid'         => true,
        ];
    }

    public function notifyResponse(): string
    {
        return 'ok';
    }

    private function sign(array $params): string
    {
        $source = $this->canonical($params);
        $key = $this->loadPrivateKey();
        if ($key === false) {
            return '';
        }
        $signature = '';
        openssl_sign($source, $signature, $key, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    private function verifySig(array $params): bool
    {
        $sig = (string) ($params['signature'] ?? '');
        $raw = base64_decode($sig, true);
        if ($raw === false) {
            return false;
        }
        $source = $this->canonical($params);
        $pub = $this->loadPublicKey();
        if ($pub === false) {
            return false;
        }
        return openssl_verify($source, $raw, $pub, OPENSSL_ALGO_SHA256) === 1;
    }

    private function canonical(array $params): string
    {
        $filtered = [];
        foreach ($params as $k => $v) {
            // 空值、signature、signMethod 不计入签名原文
            if ($k === 'signature' || $k === 'signMethod') {
                continue;
            }
            if ($v === null || $v === '') {
                continue;
            }
            $filtered[$k] = $v;
        }
        ksort($filtered);
        $pairs = [];
        foreach ($filtered as $k => $v) {
            $pairs[] = $k . '=' . $v;
        }
        return implode('&', $pairs);
    }

    private function loadPrivateKey()
    {
        $pem = $this->pem((string) $this->cfg('private_key'), 'PRIVATE KEY');
        if ($pem === '') {
            return false;
        }
        $pass = (string) $this->cfg('key_pass', '');
        return openssl_pkey_get_private($pem, $pass !== '' ? $pass : '');
    }

    private function loadPublicKey()
    {
        $pem = $this->pem((string) $this->cfg('unionpay_pub'), 'PUBLIC KEY');
        if ($pem === '') {
            return false;
        }
        return openssl_pkey_get_public($pem);
    }

    private function pem(string $raw, string $head): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (strpos($raw, '-----BEGIN') !== false) {
            return $raw;
        }
        $body = chunk_split(preg_replace('/\s+/', '', $raw) ?? '', 64, "\n");
        return '-----BEGIN ' . $head . "-----\n" . $body . '-----END ' . $head . "-----\n";
    }
}
