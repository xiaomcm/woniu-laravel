<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Storage;

use App\Core\Http;

class QiniuStorage extends Driver
{
    private function sign(string $data): string
    {
        $sign = hash_hmac('sha1', $data, $this->conf('secret'), true);
        return str_replace(['+', '/'], ['-', '_'], base64_encode($sign));
    }

    private function uploadToken(string $key): string
    {
        $policy = json_encode([
            'scope'    => $this->conf('bucket') . ':' . $key,
            'deadline' => time() + 3600,
        ], JSON_UNESCAPED_SLASHES);
        $encoded = str_replace(['+', '/'], ['-', '_'], base64_encode((string) $policy));
        return $this->conf('key') . ':' . $this->sign($encoded) . ':' . $encoded;
    }

    public function put(string $path, string $content): bool
    {
        $key = ltrim($path, '/');
        $tmp = tempnam(sys_get_temp_dir(), 'woniu');
        if ($tmp === false) {
            return false;
        }
        file_put_contents($tmp, $content);
        $res = Http::multipart($this->conf('upload_url', 'https://upload.qiniup.com'), [
            'token' => $this->uploadToken($key),
            'key'   => $key,
        ], [
            'file' => ['path' => $tmp, 'name' => basename($key)],
        ], 60);
        @unlink($tmp);
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    public function delete(string $path): bool
    {
        $key = ltrim($path, '/');
        $entry = $this->conf('bucket') . ':' . $key;
        $encodedEntry = str_replace(['+', '/'], ['-', '_'], base64_encode($entry));
        $sign = $this->sign($encodedEntry . "\n");

        $res = Http::request(
            'POST',
            'https://rs.qiniuapi.com/delete/' . $encodedEntry,
            ['Authorization' => 'QBox ' . $this->conf('key') . ':' . $sign]
        );
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    public function url(string $path): string
    {
        $domain = $this->customDomain();
        return rtrim($domain, '/') . '/' . ltrim($path, '/');
    }

    public function test(): array
    {
        if ($this->conf('key') === '' || $this->conf('secret') === '' || $this->conf('bucket') === '') {
            return $this->fail('未填写 AccessKey / SecretKey / Bucket');
        }
        if ($this->customDomain() === '') {
            return $this->fail('七牛必须填写访问域名（domain）');
        }
        $probe = '.woniu_probe_' . time() . '.txt';
        if (!$this->put($probe, 'woniu probe')) {
            return $this->fail('上传失败，请检查密钥、Bucket 与区域上传地址');
        }
        $this->delete($probe);
        return $this->ok('七牛云 Kodo 连接成功');
    }
}
