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
 * 容联云通讯（Cloopen）模板短信。
 * sig = MD5(accountSid + authToken + timestamp)，鉴权头 Base64(accountSid:timestamp)。
 */
class RonglianSms extends SmsDriver
{
    public function name(): string
    {
        return '容联云通讯';
    }

    public function send(string $phone, string $code, array $params = []): Result
    {
        $sid = (string) $this->cfg('account_sid');
        $token = (string) $this->cfg('auth_token');
        if ($sid === '' || $token === '') {
            return Result::fail('容联云通讯未配置 AccountSid / AuthToken');
        }

        $timestamp = date('YmdHis');
        $sig = strtoupper(md5($sid . $token . $timestamp));
        $base = rtrim((string) $this->cfg('endpoint', 'https://app.cloopen.com:8883'), '/');
        $url = $base . '/2013-12-26/Accounts/' . $sid . '/SMS/TemplateSMS?sig=' . $sig;

        // 模版变量：除验证码外，可附带有效期分钟
        $expire = (string) $this->cfg('expire_minutes', '5');
        $body = json_encode([
            'to'          => $phone,
            'templateId'  => (string) $this->cfg('template_id'),
            'appId'       => (string) $this->cfg('app_id'),
            'datas'       => [$code, $expire],
        ], JSON_UNESCAPED_UNICODE);

        $auth = base64_encode($sid . ':' . $timestamp);

        $response = Http::request('POST', $url, [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json;charset=utf-8',
            'Authorization' => $auth,
        ], $body, 15);

        if ($response['error'] !== '') {
            return Result::fail('容联云通讯网络错误：' . $response['error']);
        }
        $json = json_decode($response['body'], true);
        if (!is_array($json)) {
            return Result::fail('容联云通讯无有效返回');
        }
        if ((string) ($json['statusCode'] ?? '') !== '000000') {
            return Result::fail('容联云通讯发送失败：' . (string) ($json['statusMsg'] ?? json_encode($json, JSON_UNESCAPED_UNICODE)));
        }
        return Result::pass('短信已下发', $json);
    }
}
