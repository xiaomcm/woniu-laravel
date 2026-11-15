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
 * 京东云短信（sendBatchSMS，JDCLOUD2-HMAC-SHA256 签名）。
 */
class JdCloudSms extends SmsDriver
{
    public function name(): string
    {
        return '京东云短信';
    }

    private function endpoint(): string
    {
        return rtrim((string) $this->cfg('endpoint', 'https://sms.jdcloud-api.net'), '/');
    }

    public function send(string $phone, string $code, array $params = []): Result
    {
        $ak = (string) $this->cfg('access_key_id');
        $sk = (string) $this->cfg('access_key_secret');
        if ($ak === '' || $sk === '') {
            return Result::fail('京东云短信未配置 AccessKey');
        }

        $region = (string) $this->cfg('region', 'cn-north-1');
        $service = 'sms';
        $body = json_encode([
            'regionId'      => $region,
            'phoneNumber'   => [trim($phone)],
            'signId'        => (string) $this->cfg('sign_id'),
            'templateId'    => (string) $this->cfg('template_id'),
            'templateParam' => (object) ['code' => $code],
        ], JSON_UNESCAPED_UNICODE);

        $uri = '/sms/sendBatchSMS';
        $date = gmdate('Ymd\THis\Z');
        $auth = $this->sign('POST', $uri, $body, $date, $ak, $sk, $region, $service);
        $host = (string) parse_url($this->endpoint(), PHP_URL_HOST);

        $response = Http::request('POST', $this->endpoint() . $uri, [
            'Content-Type'   => 'application/json',
            'Host'           => $host,
            'X-Jdcloud-Date' => $date,
            'Authorization'  => $auth,
        ], $body, 15);

        if ($response['error'] !== '') {
            return Result::fail('京东云短信网络错误：' . $response['error']);
        }
        $json = json_decode($response['body'], true);
        if (!is_array($json)) {
            return Result::fail('京东云短信无有效返回');
        }
        if ((int) ($json['status'] ?? -1) !== 0 && (string) ($json['error'] ?? '') !== '') {
            return Result::fail('京东云短信发送失败：' . (string) ($json['message'] ?? json_encode($json, JSON_UNESCAPED_UNICODE)));
        }
        return Result::pass('短信已下发', $json);
    }

    private function sign(string $method, string $uri, string $body, string $date, string $ak, string $sk, string $region, string $service): string
    {
        $host = (string) parse_url($this->endpoint(), PHP_URL_HOST);
        $signedHeaders = 'content-type;host;x-jdcloud-date';
        $canonicalHeaders = 'content-type:application/json' . "\n"
            . 'host:' . $host . "\n"
            . 'x-jdcloud-date:' . $date . "\n";
        $canonicalRequest = $method . "\n" . $uri . "\n\n" . $canonicalHeaders . "\n"
            . $signedHeaders . "\n" . hash('sha256', $body);

        $scope = gmdate('Ymd') . '/' . $region . '/' . $service . '/jdcloud2_request';
        $stringToSign = "JDCLOUD2-HMAC-SHA256\n" . $date . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);

        $kDate = hash_hmac('sha256', gmdate('Ymd'), 'JDCLOUD2' . $sk, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'jdcloud2_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        return 'JDCLOUD2-HMAC-SHA256 Credential=' . $ak . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;
    }
}
