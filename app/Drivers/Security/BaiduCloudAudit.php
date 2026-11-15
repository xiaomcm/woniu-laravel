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

/**
 * 百度 AI 开放平台图像审核（img_censor）。
 * 每次调用先用 API Key / Secret Key 换取 access_token，再提交图片 URL 审核。
 */
class BaiduCloudAudit extends SecurityDriver
{
    public function name(): string
    {
        return '百度云图像审核';
    }

    public function audit(array $image): Result
    {
        $apiKey = (string) $this->cfg('api_key');
        $secretKey = (string) $this->cfg('secret_key');
        if ($apiKey === '' || $secretKey === '') {
            return Result::fail('百度云审核未配置 API Key / Secret Key');
        }

        $imageUrl = (string) ($image['url'] ?? '');
        if ($imageUrl === '') {
            return Result::pass('无外链可审，跳过');
        }

        $token = $this->getToken($apiKey, $secretKey);
        if ($token === '') {
            return Result::pass('百度云 Token 获取失败，按策略放行');
        }

        $body = 'data=' . urlencode(json_encode(['imgUrl' => $imageUrl], JSON_UNESCAPED_SLASHES));
        $response = Http::request(
            'POST',
            'https://aip.baidubce.com/api/v1/solution/direct/img_censor?access_token=' . $token,
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            $body,
            8
        );

        if ($response['error'] !== '' || $response['status'] === 0) {
            return Result::pass('审核服务不可达，按策略放行：' . $response['error']);
        }
        $json = json_decode($response['body'], true);
        if (!is_array($json)) {
            return Result::pass('审核返回无法解析，按策略放行');
        }

        $type = (int) ($json['conclusionType'] ?? 1);
        if ($type === 2) {
            return Result::fail('百度云审核判定不合规', $json);
        }
        if ($type === 3) {
            $threshold = (float) $this->cfg('threshold', '0.9');
            foreach (($json['data'] ?? []) as $item) {
                $prob = (float) ($item['probability'] ?? 0);
                if ($prob >= $threshold) {
                    return Result::fail('百度云审核疑似违规：' . (string) ($item['msg'] ?? ''), $json);
                }
            }
        }

        return Result::pass('百度云审核通过', $json);
    }

    private function getToken(string $apiKey, string $secretKey): string
    {
        $url = 'https://aip.baidubce.com/oauth/2.0/token?grant_type=client_credentials'
            . '&client_id=' . urlencode($apiKey) . '&client_secret=' . urlencode($secretKey);
        $response = Http::request('POST', $url, [], null, 8);
        if ($response['status'] !== 200) {
            return '';
        }
        $json = json_decode($response['body'], true);
        return is_array($json) ? (string) ($json['access_token'] ?? '') : '';
    }
}
