<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Storage;

use App\Core\Http;

class UpyunStorage extends Driver
{
    private function signature(string $method, string $uri, string $date, string $contentMd5, string $type = ''): string
    {
        $password = md5($this->conf('password'));
        $raw = $method . '&' . $uri . '&' . $date . '&' . $contentMd5 . '&' . $type;
        return md5($raw . '&' . $password);
    }

    private function uri(string $path): string
    {
        return '/' . $this->conf('bucket') . '/' . ltrim($path, '/');
    }

    public function put(string $path, string $content): bool
    {
        $uri = $this->uri($path);
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $md5 = md5($content);
        $signature = $this->signature('PUT', $uri, $date, $md5);

        $res = Http::request('PUT', 'https://v0.api.upyun.com' . $uri, [
            'Authorization'  => 'UPYUN ' . $this->conf('operator') . ':' . $signature,
            'Date'           => $date,
            'Content-MD5'    => $md5,
            'Content-Length' => (string) strlen($content),
        ], $content, 60);

        return $res['status'] >= 200 && $res['status'] < 300;
    }

    public function delete(string $path): bool
    {
        $uri = $this->uri($path);
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $signature = $this->signature('DELETE', $uri, $date, '');

        $res = Http::request('DELETE', 'https://v0.api.upyun.com' . $uri, [
            'Authorization' => 'UPYUN ' . $this->conf('operator') . ':' . $signature,
            'Date'          => $date,
        ]);
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    public function url(string $path): string
    {
        $domain = $this->customDomain();
        if ($domain === '') {
            $domain = 'https://' . $this->conf('bucket') . '.test.upcdn.net';
        }
        return rtrim($domain, '/') . '/' . ltrim($path, '/');
    }

    public function test(): array
    {
        if ($this->conf('operator') === '' || $this->conf('password') === '' || $this->conf('bucket') === '') {
            return $this->fail('未填写 Operator / Password / Bucket');
        }
        $probe = '.woniu_probe_' . time() . '.txt';
        if (!$this->put($probe, 'woniu probe')) {
            return $this->fail('上传失败，请检查操作员账号与密码');
        }
        $this->delete($probe);
        return $this->ok('又拍云 USS 连接成功');
    }
}
