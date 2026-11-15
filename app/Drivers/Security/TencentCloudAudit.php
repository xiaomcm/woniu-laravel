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
 * 腾讯云图片内容安全（IMS，ImageModeration，2020-12-29）。
 * TC3-HMAC-SHA256 签名，命中 block_labels 且 HitFlag=1 时不通过。
 */
class TencentCloudAudit extends SecurityDriver
{
    public function name(): string
    {
        return '腾讯云图片审核';
    }

    public function audit(array $image): Result
    {
        $secretId = (string) $this->cfg('secret_id');
        $secretKey = (string) $this->cfg('secret_key');
        if ($secretId === '' || $secretKey === '') {
            return Result::fail('腾讯云审核未配置 SecretId / SecretKey');
        }

        $imageUrl = (string) ($image['url'] ?? '');
        if ($imageUrl === '') {
            return Result::pass('无外链可审，跳过');
        }

        $endpoint = $this->cfg('endpoint', 'ims.tencentcloudapi.com');
        $region = $this->cfg('region', 'ap-guangzhou');
        $payload = json_encode(['FileUrl' => $imageUrl], JSON_UNESCAPED_SLASHES);

        $headers = $this->sign($secretId, $secretKey, $endpoint, $region, $payload);
        $response = Http::request('POST', 'https://' . $endpoint . '/', $headers, $payload, 8);

        if ($response['error'] !== '' || $response['status'] === 0) {
            return Result::pass('审核服务不可达，按策略放行：' . $response['error']);
        }
        $json = json_decode($response['body'], true);
        if (!is_array($json)) {
            return Result::pass('审核返回无法解析，按策略放行');
        }
        if (isset($json['Response']['Error'])) {
            // 接口报错（如参数问题）按放行，避免误杀上传
            return Result::pass('审核接口返回错误，按策略放行：' . (string) ($json['Response']['Error']['Message'] ?? ''));
        }

        $data = (array) ($json['Response']['Data'] ?? []);
        $hit = (int) ($data['HitFlag'] ?? 0);
        $label = (string) ($data['Label'] ?? '');
        $blockLabels = array_filter(array_map('trim', explode(',', $this->cfg('block_labels', 'Porn,Sexy,Terrorism,Politics,Abuse'))));

        if ($hit === 1 && $label !== '' && in_array($label, $blockLabels, true)) {
            return Result::fail('命中违规标签：' . $label, $data);
        }

        return Result::pass('腾讯云审核通过', $data);
    }

    private function sign(string $secretId, string $secretKey, string $endpoint, string $region, string $payload): array
    {
        $service = 'ims';
        $action = 'ImageModeration';
        $version = '2020-12-29';
        $timestamp = time();
        $date = gmdate('Y-m-d', $timestamp);

        $hashedPayload = hash('sha256', $payload);
        $canonicalHeaders = "content-type:application/json; charset=utf-8\nhost:" . $endpoint . "\n";
        $signedHeaders = 'content-type;host';
        $canonicalRequest = "POST\n/\n\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $hashedPayload;

        $credentialScope = $date . '/' . $service . '/tc3_request';
        $stringToSign = "TC3-HMAC-SHA256\n" . $timestamp . "\n" . $credentialScope . "\n" . hash('sha256', $canonicalRequest);

        $secretDate = hash_hmac('sha256', $date, 'TC3' . $secretKey, true);
        $secretService = hash_hmac('sha256', $service, $secretDate, true);
        $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
        $signature = hash_hmac('sha256', $stringToSign, $secretSigning);

        $authorization = 'TC3-HMAC-SHA256 Credential=' . $secretId . '/' . $credentialScope
            . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

        $headers = [
            'Authorization'   => $authorization,
            'Content-Type'    => 'application/json; charset=utf-8',
            'Host'            => $endpoint,
            'X-TC-Action'     => $action,
            'X-TC-Version'    => $version,
            'X-TC-Timestamp'  => (string) $timestamp,
        ];
        if ($region !== '') {
            $headers['X-TC-Region'] = $region;
        }
        return $headers;
    }
}
