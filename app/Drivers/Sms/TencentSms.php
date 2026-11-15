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

class TencentSms extends SmsDriver
{
    public function name(): string
    {
        return '腾讯云短信';
    }

    public function send(string $phone, string $code, array $params = []): Result
    {
        $secretId = (string) $this->cfg('secret_id');
        $secretKey = (string) $this->cfg('secret_key');
        if ($secretId === '' || $secretKey === '') {
            return Result::fail('短信未配置 SecretId');
        }

        $host = 'sms.tencentcloudapi.com';
        $payload = json_encode([
            'PhoneNumberSet'   => ['+86' . ltrim($phone, '+86')],
            'SmsSdkAppId'      => (string) $this->cfg('sdk_app_id'),
            'SignName'         => (string) $this->cfg('sign_name'),
            'TemplateId'       => (string) $this->cfg('template_id'),
            'TemplateParamSet' => [$code],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $timestamp = time();
        $date = gmdate('Y-m-d', $timestamp);
        $contentType = 'application/json; charset=utf-8';

        $canonicalRequest = "POST\n/\n\ncontent-type:" . $contentType . "\nhost:" . $host . "\n\ncontent-type;host\n" . hash('sha256', (string) $payload);
        $stringToSign = "TC3-HMAC-SHA256\n" . $timestamp . "\n" . $date . "/sms/tc3_request\n" . hash('sha256', $canonicalRequest);

        $secretDate = hash_hmac('sha256', $date, 'TC3' . $secretKey, true);
        $secretService = hash_hmac('sha256', 'sms', $secretDate, true);
        $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
        $signature = hash_hmac('sha256', $stringToSign, $secretSigning);

        $authorization = 'TC3-HMAC-SHA256 Credential=' . $secretId . '/' . $date . '/sms/tc3_request'
            . ', SignedHeaders=content-type;host, Signature=' . $signature;

        $response = Http::request('POST', 'https://' . $host . '/', [
            'Authorization'  => $authorization,
            'Content-Type'   => $contentType,
            'Host'           => $host,
            'X-TC-Action'    => 'SendSms',
            'X-TC-Version'   => '2021-01-11',
            'X-TC-Timestamp' => (string) $timestamp,
            'X-TC-Region'    => (string) $this->cfg('region', 'ap-guangzhou'),
        ], (string) $payload, 15);

        $json = json_decode($response['body'], true);
        $resp = is_array($json) ? (array) ($json['Response'] ?? []) : [];
        if (!empty($resp['Error'])) {
            return Result::fail('短信发送失败：' . (string) ($resp['Error']['Message'] ?? ''));
        }
        if (empty($resp['SendStatusSet'][0]) || (string) ($resp['SendStatusSet'][0]['Code'] ?? '') !== 'Ok') {
            return Result::fail('短信发送失败：' . (string) ($resp['SendStatusSet'][0]['Message'] ?? '返回异常'));
        }
        return Result::pass('短信已下发');
    }
}
