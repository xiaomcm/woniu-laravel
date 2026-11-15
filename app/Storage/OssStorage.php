<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Storage;

use App\Core\Http;

class OssStorage extends Driver
{
    private function endpoint(string $bucket = ''): string
    {
        $endpoint = $this->conf('endpoint');
        if ($endpoint === '') {
            $endpoint = 'oss-cn-hangzhou.aliyuncs.com';
        }
        $endpoint = preg_replace('#^https?://#', '', $endpoint);
        $bucket = $bucket !== '' ? $bucket : $this->conf('bucket');
        return 'https://' . $bucket . '.' . $endpoint;
    }

    public function put(string $path, string $content): bool
    {
        $path = ltrim($path, '/');
        $object = '/' . $this->conf('bucket') . '/' . $path;
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $contentType = 'application/octet-stream';

        $stringToSign = "PUT\n\n" . $contentType . "\n" . $date . "\n" . $object;
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->conf('secret'), true));

        $res = Http::request('PUT', $this->endpoint() . '/' . $path, [
            'Date'           => $date,
            'Content-Type'   => $contentType,
            'Content-Length' => (string) strlen($content),
            'Authorization'  => 'OSS ' . $this->conf('key') . ':' . $signature,
        ], $content, 60);

        return $res['status'] >= 200 && $res['status'] < 300;
    }

    public function delete(string $path): bool
    {
        $path = ltrim($path, '/');
        $object = '/' . $this->conf('bucket') . '/' . $path;
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $stringToSign = "DELETE\n\n\n" . $date . "\n" . $object;
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->conf('secret'), true));

        $res = Http::request('DELETE', $this->endpoint() . '/' . $path, [
            'Date'          => $date,
            'Authorization' => 'OSS ' . $this->conf('key') . ':' . $signature,
        ]);
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    public function url(string $path): string
    {
        $domain = $this->customDomain();
        if ($domain !== '') {
            return $domain . '/' . ltrim($path, '/');
        }
        return $this->endpoint() . '/' . ltrim($path, '/');
    }

    public function test(): array
    {
        if ($this->conf('key') === '' || $this->conf('secret') === '' || $this->conf('bucket') === '') {
            return $this->fail('未填写 AccessKeyId / AccessKeySecret / Bucket');
        }
        $probe = '.woniu_probe_' . time() . '.txt';
        if (!$this->put($probe, 'woniu probe')) {
            return $this->fail('写入失败，请检查密钥、Bucket 与 Endpoint');
        }
        $this->delete($probe);
        return $this->ok('阿里云 OSS 连接成功');
    }
}
