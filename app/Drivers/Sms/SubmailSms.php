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
 * Submail（赛邮）短信，MD5 签名模式。
 * 文案用 {code} 占位符渲染。
 */
class SubmailSms extends SmsDriver
{
    public function name(): string
    {
        return 'Submail 赛邮';
    }

    public function send(string $phone, string $code, array $params = []): Result
    {
        $appid = (string) $this->cfg('appid');
        $appkey = (string) $this->cfg('appkey');
        if ($appid === '' || $appkey === '') {
            return Result::fail('Submail 未配置 appid / appkey');
        }

        $text = (string) $this->cfg('content', '您的验证码是 {code}');
        $text = str_replace('{code}', $code, $text);

        $data = [
            'appid'     => $appid,
            'to'        => $phone,
            'content'   => $text,
            'sign_type' => 'md5',
        ];
        $data['signature'] = $this->sign($data, $appkey);

        $endpoint = rtrim((string) $this->cfg('endpoint', 'https://api.mysubmail.com/message/send.json'), '/');

        $response = Http::request('POST', $endpoint, [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], http_build_query($data), 15);

        if ($response['error'] !== '') {
            return Result::fail('Submail 网络错误：' . $response['error']);
        }
        $json = json_decode($response['body'], true);
        if (!is_array($json)) {
            return Result::fail('Submail 无有效返回');
        }
        if ((string) ($json['status'] ?? '') !== 'success') {
            return Result::fail('Submail 发送失败：' . (string) ($json['msg'] ?? json_encode($json, JSON_UNESCAPED_UNICODE)));
        }
        return Result::pass('短信已下发', $json);
    }

    private function sign(array $data, string $appkey): string
    {
        $str = '';
        ksort($data);
        foreach ($data as $key => $value) {
            if ($key === 'signature' || $value === '') {
                continue;
            }
            $str .= $key . '=' . $value . '&';
        }
        $str = substr($str, 0, -1) . $appkey;
        return md5($str);
    }
}
