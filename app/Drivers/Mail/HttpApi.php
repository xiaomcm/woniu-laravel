<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers\Mail;

use App\Core\Http;
use App\Drivers\MailDriver;
use App\Drivers\Result;

class HttpApi extends MailDriver
{
    public function name(): string
    {
        return '自建发信接口';
    }

    public function send(string $to, string $subject, string $html): Result
    {
        $endpoint = (string) $this->cfg('endpoint');
        if ($endpoint === '') {
            return Result::fail('发信接口未配置');
        }

        $headers = ['Content-Type' => 'application/json'];
        $token = (string) $this->cfg('token');
        if ($token !== '') {
            $headers['X-Token'] = $token;
        }

        $body = json_encode([
            'to'      => $to,
            'from'    => (string) $this->cfg('from'),
            'subject' => $subject,
            'html'    => $html,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = Http::request('POST', $endpoint, $headers, (string) $body, 15);
        if ($response['error'] !== '') {
            return Result::fail('发信接口请求失败：' . $response['error']);
        }
        if ($response['status'] < 200 || $response['status'] >= 300) {
            return Result::fail('发信接口返回 ' . $response['status']);
        }
        return Result::pass('邮件已投递');
    }
}
