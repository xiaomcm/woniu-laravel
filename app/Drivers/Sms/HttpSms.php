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

class HttpSms extends SmsDriver
{
    public function name(): string
    {
        return '自建短信接口';
    }

    public function send(string $phone, string $code, array $params = []): Result
    {
        $endpoint = (string) $this->cfg('endpoint');
        if ($endpoint === '') {
            return Result::fail('短信接口未配置');
        }

        $headers = ['Content-Type' => 'application/json'];
        $token = (string) $this->cfg('token');
        if ($token !== '') {
            $headers['X-Token'] = $token;
        }

        $body = json_encode([
            'phone'    => $phone,
            'code'     => $code,
            'template' => (string) $this->cfg('template'),
            'extra'    => $params,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = Http::request('POST', $endpoint, $headers, (string) $body, 15);
        if ($response['error'] !== '') {
            return Result::fail('短信接口请求失败：' . $response['error']);
        }
        if ($response['status'] < 200 || $response['status'] >= 300) {
            return Result::fail('短信接口返回 ' . $response['status']);
        }
        $json = json_decode($response['body'], true);
        if (is_array($json) && isset($json['pass']) && $json['pass'] === false) {
            return Result::fail('短信网关拒绝：' . (string) ($json['message'] ?? ''));
        }
        return Result::pass('短信已下发');
    }
}
