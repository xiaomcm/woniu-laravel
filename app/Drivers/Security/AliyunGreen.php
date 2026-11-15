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

class AliyunGreen extends SecurityDriver
{
    private const API_VERSION = '2022-03-02';

    public function name(): string
    {
        return '阿里云内容安全';
    }

    public function audit(array $image): Result
    {
        $keyId = (string) $this->cfg('access_key_id');
        $keySecret = (string) $this->cfg('access_key_secret');
        if ($keyId === '' || $keySecret === '') {
            return Result::fail('内容安全未配置 AccessKey');
        }

        $imageUrl = (string) ($image['url'] ?? '');
        if ($imageUrl === '') {
            return Result::pass('无外链可审，跳过');
        }

        $endpoint = (string) $this->cfg('endpoint', 'green-cip.cn-shanghai.aliyuncs.com');
        $service = (string) $this->cfg('service', 'baselineCheck');
        $body = json_encode([
            'Service'           => $service,
            'ServiceParameters' => json_encode(['imageUrl' => $imageUrl], JSON_UNESCAPED_SLASHES),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $date = gmdate('Y-m-d\TH:i:s\Z');
        $nonce = bin2hex(random_bytes(16));
        $bodyHash = hash('sha256', (string) $body);

        $canonicalHeaders = 'content-type:application/json' . "\n"
            . 'host:' . $endpoint . "\n"
            . 'x-acs-action:ImageModeration' . "\n"
            . 'x-acs-content-sha256:' . $bodyHash . "\n"
            . 'x-acs-date:' . $date . "\n"
            . 'x-acs-signature-nonce:' . $nonce . "\n"
            . 'x-acs-version:' . self::API_VERSION . "\n";
        $signedHeaders = 'content-type;host;x-acs-action;x-acs-content-sha256;x-acs-date;x-acs-signature-nonce;x-acs-version';

        $canonicalRequest = 'POST' . "\n" . '/' . "\n" . '' . "\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $bodyHash;
        $stringToSign = 'ACS3-HMAC-SHA256' . "\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $keySecret);

        $authorization = 'ACS3-HMAC-SHA256 Credential=' . $keyId
            . ',SignedHeaders=' . $signedHeaders
            . ',Signature=' . $signature;

        $response = Http::request('POST', 'https://' . $endpoint . '/', [
            'Host'                 => $endpoint,
            'Content-Type'         => 'application/json',
            'x-acs-action'         => 'ImageModeration',
            'x-acs-version'        => self::API_VERSION,
            'x-acs-date'           => $date,
            'x-acs-signature-nonce' => $nonce,
            'x-acs-content-sha256' => $bodyHash,
            'Authorization'        => $authorization,
        ], (string) $body, 8);

        if ($response['error'] !== '' || $response['status'] === 0) {
            return Result::pass('审核服务不可达，按策略放行：' . $response['error']);
        }

        $json = json_decode($response['body'], true);
        if (!is_array($json)) {
            return Result::pass('审核返回无法解析，按策略放行');
        }
        if ((int) ($json['Code'] ?? 0) !== 200) {
            return Result::pass('审核返回码 ' . (string) ($json['Code'] ?? '?') . '：' . (string) ($json['Message'] ?? ''));
        }

        $blockLabels = array_filter(array_map('trim', explode(',', (string) $this->cfg('block_labels', 'porn,sexy,terrorism,politics,contraband'))));
        $results = (array) ($json['Data']['Result'] ?? []);

        foreach ($results as $item) {
            $label = (string) ($item['Label'] ?? '');
            $confidence = (float) ($item['Confidence'] ?? 0);
            if ($label !== '' && in_array($label, $blockLabels, true) && $confidence >= 60) {
                return Result::fail('命中违规标签：' . $label . '（' . round($confidence) . '%）', $results);
            }
        }

        return Result::pass('内容安全通过', $results);
    }
}
