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
 * Twilio 短信（Messages API），国际短信通道。
 * Basic 鉴权，body 文案用 {code} 占位符渲染。
 */
class TwilioSms extends SmsDriver
{
    public function name(): string
    {
        return 'Twilio';
    }

    public function send(string $phone, string $code, array $params = []): Result
    {
        $sid = (string) $this->cfg('account_sid');
        $token = (string) $this->cfg('auth_token');
        $from = (string) $this->cfg('from_number');
        if ($sid === '' || $token === '' || $from === '') {
            return Result::fail('Twilio 未配置 AccountSid / AuthToken / From');
        }

        $text = (string) $this->cfg('message', 'Your verification code is {code}');
        $text = str_replace('{code}', $code, $text);

        $fields = http_build_query([
            'To'   => $phone,
            'From' => $from,
            'Body' => $text,
        ]);

        $response = Http::request(
            'POST',
            'https://api.twilio.com/2010-04-01/Accounts/' . $sid . '/Messages.json',
            [
                'Authorization' => 'Basic ' . base64_encode($sid . ':' . $token),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            $fields,
            15
        );

        if ($response['error'] !== '') {
            return Result::fail('Twilio 网络错误：' . $response['error']);
        }
        $json = json_decode($response['body'], true);
        if (!is_array($json)) {
            return Result::fail('Twilio 无有效返回');
        }
        if (isset($json['error_code']) && $json['error_code'] !== null) {
            return Result::fail('Twilio 发送失败：' . (string) ($json['error_message'] ?? json_encode($json, JSON_UNESCAPED_UNICODE)));
        }
        return Result::pass('短信已下发', $json);
    }
}
