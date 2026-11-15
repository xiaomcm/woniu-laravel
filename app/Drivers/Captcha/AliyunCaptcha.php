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
use App\Drivers\Sms\AliyunSms;
use App\Services\CodeService;

/**
 * 阿里云验证码。
 *
 * slide：前端验证码 2.0（initAliyunCaptcha）回调 captchaVerifyParam，
 *        服务端用 POP 签名调 VerifyIntelligentCaptcha（2023-03-05）做二次校验。
 * sms  ：复用阿里云短信（SendSms）下发 6 位验证码，走 CodeService 的限流与会话存储。
 */
class AliyunCaptcha extends CaptchaDriver
{
    public function name(): string
    {
        return '阿里云验证码';
    }

    public function mode(): string
    {
        $mode = (string) $this->cfg('mode', 'slide');
        return $mode === 'sms' ? 'sms' : 'slide';
    }

    public function frontend(): array
    {
        if ($this->mode() === 'sms') {
            return ['kind' => 'sms', 'provider' => 'aliyun'];
        }
        return [
            'kind'     => 'slide',
            'provider' => 'aliyun',
            'scene_id' => (string) $this->cfg('scene_id'),
            'prefix'   => (string) $this->cfg('prefix'),
            'region'   => (string) $this->cfg('aliyun_region', 'cn-hangzhou'),
        ];
    }

    public function verify(array $input): Result
    {
        return $this->mode() === 'sms'
            ? $this->verifySms($input)
            : $this->verifySlide($input);
    }

    /** 短信验证码驱动：用设置页的参数直接构造，不依赖 drivers 表 */
    public function smsDriver(): AliyunSms
    {
        return new AliyunSms([
            'access_key_id'     => (string) $this->cfg('access_key_id'),
            'access_key_secret' => (string) $this->cfg('access_key_secret'),
            'sign_name'         => (string) $this->cfg('aliyun_sign_name'),
            'template_code'     => (string) $this->cfg('aliyun_template'),
            'region'            => (string) $this->cfg('aliyun_region', 'cn-hangzhou'),
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
        $token = trim((string) ($input['captcha_verify_param'] ?? ''));
        if ($token === '') {
            return Result::fail('请先完成滑块验证');
        }

        $accessId = (string) $this->cfg('access_key_id');
        $accessSecret = (string) $this->cfg('access_key_secret');
        $sceneId = (string) $this->cfg('scene_id');
        if ($accessId === '' || $accessSecret === '' || $sceneId === '') {
            return Result::fail('阿里云验证码未配置完整（AccessKey / SceneId）');
        }

        $query = [
            'AccessKeyId'         => $accessId,
            'Action'              => 'VerifyIntelligentCaptcha',
            'CaptchaVerifyParam'  => $token,
            'Format'              => 'JSON',
            'SceneId'             => $sceneId,
            'SignatureMethod'     => 'HMAC-SHA1',
            'SignatureNonce'      => bin2hex(random_bytes(8)),
            'SignatureVersion'    => '1.0',
            'Timestamp'           => gmdate('Y-m-d\TH:i:s\Z'),
            'Version'             => '2023-03-05',
        ];
        ksort($query);
        $pairs = [];
        foreach ($query as $key => $value) {
            $pairs[] = $this->encode((string) $key) . '=' . $this->encode((string) $value);
        }
        $canonical = implode('&', $pairs);
        $stringToSign = 'GET&%2F&' . $this->encode($canonical);
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessSecret . '&', true));

        // 用与签名完全相同的编码拼 URL，避免 http_build_query 的 + 与 %20 不一致
        $endpoint = (string) $this->cfg('endpoint', 'captcha.cn-shanghai.aliyuncs.com');
        $url = 'https://' . $endpoint . '/?' . $canonical . '&Signature=' . rawurlencode($signature);

        $response = Http::request('GET', $url, [], null, 10);
        if ($response['error'] !== '' || $response['status'] === 0) {
            return Result::fail('验证码校验服务不可达：' . $response['error']);
        }
        $json = json_decode($response['body'], true);
        if (!is_array($json)) {
            return Result::fail('验证码校验返回无法解析');
        }

        $success = ($json['Success'] ?? false) === true;
        $code = (string) ($json['Code'] ?? '');
        $verifyResult = $json['Result']['VerifyResult'] ?? null;
        if (!$success || ($code !== '' && $code !== 'Success') || $verifyResult === false) {
            $msg = (string) ($json['Message'] ?? '');
            if ($msg === '' || $msg === 'success') {
                $msg = $code !== '' && $code !== 'Success' ? $code : '票据无效或已过期';
            }
            return Result::fail('滑块验证未通过：' . $msg);
        }
        return Result::pass('验证通过');
    }

    /** 阿里云 POP 签名要求的 RFC3986 编码（空格 %20、* %2A、~ 不编码） */
    private function encode(string $value): string
    {
        return str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], rawurlencode($value));
    }
}
