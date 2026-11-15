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

class AliyunSms extends SmsDriver
{
    public function name(): string
    {
        return '阿里云短信';
    }

    public function send(string $phone, string $code, array $params = []): Result
    {
        $accessId = (string) $this->cfg('access_key_id');
        $accessSecret = (string) $this->cfg('access_key_secret');
        if ($accessId === '' || $accessSecret === '') {
            return Result::fail('短信未配置 AccessKey');
        }

        $query = [
            'AccessKeyId'      => $accessId,
            'Action'           => 'SendSms',
            'Format'           => 'JSON',
            'PhoneNumbers'     => $phone,
            'RegionId'         => (string) $this->cfg('region', 'cn-hangzhou'),
            'SignName'         => (string) $this->cfg('sign_name'),
            'SignatureMethod'  => 'HMAC-SHA1',
            'SignatureNonce'   => bin2hex(random_bytes(8)),
            'SignatureVersion' => '1.0',
            'TemplateCode'     => (string) $this->cfg('template_code'),
            'TemplateParam'    => json_encode(['code' => $code], JSON_UNESCAPED_UNICODE),
            'Timestamp'        => gmdate('Y-m-d\TH:i:s\Z'),
            'Version'          => '2017-05-25',
        ];

        ksort($query);
        $pairs = [];
        foreach ($query as $key => $value) {
            $pairs[] = $this->encode((string) $key) . '=' . $this->encode((string) $value);
        }
        $stringToSign = 'GET&%2F&' . $this->encode(implode('&', $pairs));
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessSecret . '&', true));
        $query['Signature'] = $signature;

        $response = Http::request('GET', 'https://dysmsapi.aliyuncs.com/?' . http_build_query($query));
        $json = json_decode($response['body'], true);
        if (!is_array($json)) {
            return Result::fail('短信接口无有效返回');
        }
        if ((string) ($json['Code'] ?? '') !== 'OK') {
            return Result::fail('短信发送失败：' . (string) ($json['Message'] ?? $json['Code'] ?? ''));
        }
        return Result::pass('短信已下发');
    }

    private function encode(string $value): string
    {
        return str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], rawurlencode($value));
    }
}
