<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Storage;

use App\Core\Http;

class S3Storage extends Driver
{
    private function region(): string
    {
        return $this->conf('region', 'us-east-1');
    }

    private function bucket(): string
    {
        return $this->conf('bucket');
    }

    protected function endpointParts(): array
    {
        $endpoint = $this->conf('endpoint', 'https://s3.amazonaws.com');
        if (!preg_match('#^https?://#i', $endpoint)) {
            $endpoint = 'https://' . $endpoint;
        }
        $parsed = parse_url($endpoint);
        return [
            'scheme' => $parsed['scheme'] ?? 'https',
            'host'   => $parsed['host'] ?? '',
            'port'   => isset($parsed['port']) ? ':' . $parsed['port'] : '',
            'path'   => rtrim($parsed['path'] ?? '', '/'),
        ];
    }

    protected function pathStyle(): bool
    {
        return !empty($this->config['path_style']);
    }



    private function resolve(string $path): array
    {
        $parts = $this->endpointParts();
        $bucket = $this->bucket();
        $path = ltrim($path, '/');

        if ($this->pathStyle()) {
            $uri = $parts['path'] . '/' . $bucket . '/' . $path;
            $host = $parts['host'] . $parts['port'];
        } else {
            $uri = $parts['path'] . '/' . $path;
            $host = $bucket . '.' . $parts['host'] . $parts['port'];
        }
        $uri = '/' . ltrim($uri, '/');
        return [
            'url'  => $parts['scheme'] . '://' . $host . $uri,
            'host' => $host,
            'uri'  => $uri,
        ];
    }

    private function sign(string $method, string $path, string $payloadHash, string $contentType = ''): array
    {
        $accessKey = $this->conf('key');
        $secretKey = $this->conf('secret');
        $region = $this->region();
        $service = 's3';

        $target = $this->resolve($path);
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = substr($amzDate, 0, 8);

        $canonicalHeaders = "host:" . $target['host'] . "\n"
            . "x-amz-content-sha256:" . $payloadHash . "\n"
            . "x-amz-date:" . $amzDate . "\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';

        $canonicalRequest = $method . "\n"
            . $target['uri'] . "\n"
            . "\n"
            . $canonicalHeaders . "\n"
            . $signedHeaders . "\n"
            . $payloadHash;

        $credentialScope = $dateStamp . '/' . $region . '/' . $service . '/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $credentialScope . "\n" . hash('sha256', $canonicalRequest);

        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $headers = [
            'Host'                 => $target['host'],
            'x-amz-date'           => $amzDate,
            'x-amz-content-sha256' => $payloadHash,
            'Authorization'        => 'AWS4-HMAC-SHA256 Credential=' . $accessKey . '/' . $credentialScope
                . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature,
        ];
        if ($contentType !== '') {
            $headers['Content-Type'] = $contentType;
        }

        return ['url' => $target['url'], 'headers' => $headers];
    }

    public function put(string $path, string $content): bool
    {
        $payloadHash = hash('sha256', $content);
        $signed = $this->sign('PUT', $path, $payloadHash, 'application/octet-stream');
        $res = Http::request('PUT', $signed['url'], $signed['headers'], $content, 60);
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    public function delete(string $path): bool
    {
        $emptyHash = hash('sha256', '');
        $signed = $this->sign('DELETE', $path, $emptyHash);
        $res = Http::request('DELETE', $signed['url'], $signed['headers']);
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    public function url(string $path): string
    {
        $domain = $this->customDomain();
        if ($domain !== '') {
            return $domain . '/' . ltrim($path, '/');
        }
        return $this->resolve($path)['url'];
    }

    public function test(): array
    {
        if ($this->conf('key') === '' || $this->conf('secret') === '' || $this->bucket() === '') {
            return $this->fail('未填写 AccessKey / SecretKey / Bucket');
        }
        $probe = '.woniu_probe_' . time() . '.txt';
        if (!$this->put($probe, 'woniu probe')) {
            return $this->fail('写入失败，请检查密钥、Bucket、Region 与 Endpoint');
        }
        $this->delete($probe);
        return $this->ok('S3 连接成功');
    }
}
