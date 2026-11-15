<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers\Captcha;

use App\Core\Http;
use App\Drivers\CaptchaDriver;
use App\Drivers\Result;
use App\Drivers\Sms\TencentSms;
use App\Services\CodeService;

/**
 * 腾讯云验证码。
 *
 * slide：前端 TCaptcha.js 拿到 ticket / randstr，服务端用 TC3-HMAC-SHA256 调
 *        DescribeCaptchaResult（2019-07-22）做票据二次校验。
 * sms  ：复用腾讯云短信（SendSms）下发 6 位验证码，走 CodeService 的限流与会话存储。
 */
class TencentCaptcha extends CaptchaDriver
{
    public function name(): string
    {
        return '腾讯云验证码';
    }

    public function mode(): string
    {
        $mode = (string) $this->cfg('mode', 'slide');
        return $mode === 'sms' ? 'sms' : 'slide';
    }

    public function frontend(): array
    {
        if ($this->mode() === 'sms') {
            return ['kind' => 'sms', 'provider' => 'tencent'];
        }
        return [
            'kind'     => 'slide',
            'provider' => 'tencent',
            'app_id'   => (string) $this->cfg('app_id'),
        ];
    }

    public function verify(array $input): Result
    {
        return $this->mode() === 'sms'
            ? $this->verifySms($input)
            : $this->verifySlide($input);
    }

    /** 短信验证码驱动：用设置页的参数直接构造，不依赖 drivers 表 */
    public function smsDriver(): TencentSms
    {
        return new TencentSms([
            'secret_id'   => (string) $this->cfg('secret_id'),
            'secret_key'  => (string) $this->cfg('secret_key'),
            'sdk_app_id'  => (string) $this->cfg('sdk_app_id'),
            'sign_name'   => (string) $this->cfg('sign_name'),
            'template_id' => (string) $this->cfg('template_id'),
            'region'      => (string) $this->cfg('region', 'ap-guangzhou'),
        ]);
    }

    private function verifySms(array $input): Result
    {
        $phone = trim((string) ($input['phone'] ?? ''));
        $code = trim((string) ($input['captcha_sms_code'] ?? ''));
        if ($phone === '' || $code === '') {
            return Result::fail('请填写手机号与短信验证码');
        }
        if (!CodeService::verify('sms', $phone, 'register_captcha', $code)) {
            return Result::fail('短信验证码错误或已过期');
        }
        return Result::pass('验证通过');
    }

    private function verifySlide(array $input): Result
    {
        $ticket = trim((string) ($input['captcha_ticket'] ?? ''));
        $randstr = trim((string) ($input['captcha_randstr'] ?? ''));
        if ($ticket === '' || $randstr === '') {
            return Result::fail('请先完成滑块验证');
        }

        $appId = (string) $this->cfg('app_id');
        $appSecret = (string) $this->cfg('app_secret');
        $secretId = (string) $this->cfg('secret_id');
        $secretKey = (string) $this->cfg('secret_key');
        if ($appId === '' || $appSecret === '' || $secretId === '' || $secretKey === '') {
            return Result::fail('腾讯云验证码未配置完整（AppId / AppSecretKey / SecretId / SecretKey）');
        }

        $payload = json_encode([
            'CaptchaType'        => 9,
            'Ticket'             => $ticket,
            'Randstr'            => $randstr,
            'CaptchaAppId'       => (int) $appId,
            'AppSecretKey'       => $appSecret,
            'UserIp'             => (string) ($input['_ip'] ?? '127.0.0.1'),
            'NeedGetCaptchaTime' => 1,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $headers = $this->tc3Sign($secretId, $secretKey, (string) $payload);
        $response = Http::request(
            'POST',
            'https://captcha.tencentcloudapi.com/',
            $headers,
            (string) $payload,
            10
        );

        if ($response['error'] !== '' || $response['status'] === 0) {
            return Result::fail('验证码校验服务不可达：' . $response['error']);
        }
        $json = json_decode($response['body'], true);
        $resp = is_array($json) ? (array) ($json['Response'] ?? []) : [];
        if (!empty($resp['Error'])) {
            return Result::fail('验证码校验失败：' . (string) ($resp['Error']['Message'] ?? ''));
        }
        // CaptchaCode = 1 为通过；其余见腾讯云 7/8/9/15/16/21/100 等错误码
        if ((int) ($resp['CaptchaCode'] ?? 0) !== 1) {
            return Result::fail('滑块验证未通过：' . (string) ($resp['CaptchaMsg'] ?? '票据无效或已过期'));
        }
        return Result::pass('验证通过');
    }

    /** 腾讯云 TC3-HMAC-SHA256 签名，service = captcha */
    private function tc3Sign(string $secretId, string $secretKey, string $payload): array
    {
        $host = 'captcha.tencentcloudapi.com';
        $timestamp = time();
        $date = gmdate('Y-m-d', $timestamp);
        $contentType = 'application/json; charset=utf-8';

        $canonical = "POST\n/\n\ncontent-type:" . $contentType . "\nhost:" . $host
            . "\n\ncontent-type;host\n" . hash('sha256', $payload);
        $toSign = "TC3-HMAC-SHA256\n" . $timestamp . "\n" . $date . "/captcha/tc3_request\n"
            . hash('sha256', $canonical);

        $secretDate = hash_hmac('sha256', $date, 'TC3' . $secretKey, true);
        $secretService = hash_hmac('sha256', 'captcha', $secretDate, true);
        $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
        $signature = hash_hmac('sha256', $toSign, $secretSigning);

        return [
            'Authorization'  => 'TC3-HMAC-SHA256 Credential=' . $secretId . '/' . $date
                . '/captcha/tc3_request, SignedHeaders=content-type;host, Signature=' . $signature,
            'Content-Type'   => $contentType,
            'Host'           => $host,
            'X-TC-Action'    => 'DescribeCaptchaResult',
            'X-TC-Version'   => '2019-07-22',
            'X-TC-Timestamp' => (string) $timestamp,
            'X-TC-Region'    => (string) $this->cfg('region', 'ap-guangzhou'),
        ];
    }
}
