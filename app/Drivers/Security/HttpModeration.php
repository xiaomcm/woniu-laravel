<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers\Security;

use App\Core\Http;
use App\Drivers\Result;
use App\Drivers\SecurityDriver;

class HttpModeration extends SecurityDriver
{
    public function name(): string
    {
        return '自建审核接口';
    }

    public function audit(array $image): Result
    {
        $endpoint = (string) $this->cfg('endpoint');
        if ($endpoint === '') {
            return Result::fail('审核接口地址未配置');
        }

        $headers = ['Content-Type' => 'application/json'];
        $token = (string) $this->cfg('token');
        if ($token !== '') {
            $headers['X-Token'] = $token;
        }

        $payload = json_encode([
            'url'      => (string) ($image['url'] ?? ''),
            'name'     => (string) ($image['name'] ?? ''),
            'mimetype' => (string) ($image['mimetype'] ?? ''),
            'sha1'     => (string) ($image['sha1'] ?? ''),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = Http::request('POST', $endpoint, $headers, (string) $payload, max(1, $this->int('timeout', 5)));
        if ($response['error'] !== '' || $response['status'] === 0) {
            return Result::pass('审核接口不可达，按策略放行');
        }

        $json = json_decode($response['body'], true);
        if (!is_array($json)) {
            return Result::pass('审核结果非 JSON，按策略放行');
        }

        $field = (string) $this->cfg('pass_field', 'pass');
        $pass = $json[$field] ?? null;
        if ($pass === false || $pass === 0 || $pass === 'false') {
            $label = (string) ($json['label'] ?? '违规');
            return Result::fail('审核未通过：' . $label, (array) $json);
        }

        return Result::pass('外部审核通过', (array) $json);
    }
}
