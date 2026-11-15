<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Storage;

use App\Core\Http;

class CosStorage extends Driver
{
    private function host(): string
    {
        if ($this->conf('endpoint') !== '') {
            return preg_replace('#^https?://#', '', $this->conf('endpoint'));
        }
        return $this->conf('bucket') . '.cos.' . $this->conf('region', 'ap-guangzhou') . '.myqcloud.com';
    }

    public function put(string $path, string $content): bool
    {
        $path = '/' . ltrim($path, '/');
        $host = $this->host();
        $signTime = time() . ';' . (time() + 3600);
        $signKey = hash_hmac('sha1', $signTime, $this->conf('secret'));

        $httpString = "put\n" . $path . "\n\nhost=" . $host . "\n";
        $stringToSign = "sha1\n" . $signTime . "\n" . sha1($httpString) . "\n";
        $signature = hash_hmac('sha1', $stringToSign, $signKey);

        $authorization = 'q-sign-algorithm=sha1'
            . '&q-ak=' . $this->conf('key')
            . '&q-sign-time=' . $signTime
            . '&q-key-time=' . $signTime
            . '&q-header-list=host'
            . '&q-url-param-list='
            . '&q-signature=' . $signature;

        $res = Http::request('PUT', 'https://' . $host . $path, [
            'Host'            => $host,
            'Authorization'   => $authorization,
            'Content-Type'    => 'application/octet-stream',
            'Content-Length'  => (string) strlen($content),
        ], $content, 60);

        return $res['status'] >= 200 && $res['status'] < 300;
    }

    public function delete(string $path): bool
    {
        $path = '/' . ltrim($path, '/');
        $host = $this->host();
        $signTime = time() . ';' . (time() + 3600);
        $signKey = hash_hmac('sha1', $signTime, $this->conf('secret'));
        $httpString = "delete\n" . $path . "\n\nhost=" . $host . "\n";
        $stringToSign = "sha1\n" . $signTime . "\n" . sha1($httpString) . "\n";
        $signature = hash_hmac('sha1', $stringToSign, $signKey);

        $authorization = 'q-sign-algorithm=sha1&q-ak=' . $this->conf('key')
            . '&q-sign-time=' . $signTime . '&q-key-time=' . $signTime
            . '&q-header-list=host&q-url-param-list=&q-signature=' . $signature;

        $res = Http::request('DELETE', 'https://' . $host . $path, [
            'Host'          => $host,
            'Authorization' => $authorization,
        ]);
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    public function url(string $path): string
    {
        $domain = $this->customDomain();
        if ($domain !== '') {
            return $domain . '/' . ltrim($path, '/');
        }
        return 'https://' . $this->host() . '/' . ltrim($path, '/');
    }

    public function test(): array
    {
        if ($this->conf('key') === '' || $this->conf('secret') === '') {
            return $this->fail('未填写 SecretId / SecretKey');
        }
        $probe = '.woniu_probe_' . time() . '.txt';
        if (!$this->put($probe, 'woniu probe')) {
            return $this->fail('写入失败，请检查密钥、Bucket、Region');
        }
        $this->delete($probe);
        return $this->ok('腾讯云 COS 连接成功');
    }
}
