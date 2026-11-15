<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers\Sms;

use App\Core\Http;
use App\Drivers\Result;
use App\Drivers\SmsDriver;

/**
 * 华为云短信（batchSendSms）。
 * AK/SK 签名（SDK-HMAC-SHA256），手机号需 E.164 格式（默认补 +86）。
 */
class HuaweiCloudSms extends SmsDriver
{
    public function name(): string
    {
        return '华为云短信';
    }

    private function endpoint(): string
    {
        return rtrim((string) $this->cfg('endpoint', 'https://smsapi.cn-north-4.myhuaweicloud.com'), '/');
    }

    public function send(string $phone, string $code, array $params = []): Result
    {
        $ak = (string) $this->cfg('access_key_id');
        $sk = (string) $this->cfg('access_key_secret');
        if ($ak === '' || $sk === '') {
            return Result::fail('华为云短信未配置 AccessKey');
        }

        $number = $this->normalizePhone($phone);
        $body = json_encode([
            'sender'        => (string) $this->cfg('sender'),
            'signature'     => (string) $this->cfg('sign_name', $this->cfg('sender')),
            'templateId'    => (string) $this->cfg('template_id'),
            'phoneNumber'   => [$number],
            'templateParam' => json_encode(['code' => $code], JSON_UNESCAPED_UNICODE),
        ], JSON_UNESCAPED_UNICODE);

        $uri = '/sms/batchSendSms/v1';
        $date = gmdate('Ymd\THis\Z');
        $auth = $this->sign('POST', $uri, $body, $date, $ak, $sk);
        $host = (string) parse_url($this->endpoint(), PHP_URL_HOST);

        $response = Http::request('POST', $this->endpoint() . $uri, [
            'Content-Type' => 'application/json',
            'Host'         => $host,
            'X-Sdk-Date'   => $date,
            'Authorization' => $auth,
        ], $body, 15);

        if ($response['error'] !== '') {
            return Result::fail('华为云短信网络错误：' . $response['error']);
        }
        $json = json_decode($response['body'], true);
        if (!is_array($json)) {
            return Result::fail('华为云短信无有效返回');
        }
        // 返回结果在 result 数组中；非零 code 即失败
        $result = $json['result'] ?? [];
        $codeField = is_array($result) ? (string) ($result['code'] ?? '') : '';
        if ($codeField !== '000000') {
            return Result::fail('华为云短信发送失败：' . (string) ($json['description'] ?? json_encode($json, JSON_UNESCAPED_UNICODE)));
        }
        return Result::pass('短信已下发', $json);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        if (str_starts_with($phone, '+')) {
            return $phone;
        }
        $prefix = (string) $this->cfg('phone_prefix', '+86');
        return $prefix . ltrim($phone, '0');
    }

    private function sign(string $method, string $uri, string $body, string $date, string $ak, string $sk): string
    {
        $host = (string) parse_url($this->endpoint(), PHP_URL_HOST);
        $signedHeaders = 'content-type;host;x-sdk-date';
        $canonicalHeaders = 'content-type:application/json' . "\n"
            . 'host:' . $host . "\n"
            . 'x-sdk-date:' . $date . "\n";
        $canonicalRequest = $method . "\n"
            . $uri . "\n"
            . '' . "\n"
            . $canonicalHeaders . "\n"
            . $signedHeaders . "\n"
            . hash('sha256', $body);
        $stringToSign = "SDK-HMAC-SHA256\n" . $date . "\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $sk);
        return 'SDK-HMAC-SHA256 Access=' . $ak . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;
    }
}
