<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Storage;

use App\Core\Http;

class WebdavStorage extends Driver
{
    private function base(): string
    {
        return rtrim($this->conf('url'), '/');
    }

    private function headers(): array
    {
        $headers = [];
        if ($this->conf('username') !== '') {
            $headers['Authorization'] = 'Basic ' . base64_encode($this->conf('username') . ':' . $this->conf('password'));
        }
        return $headers;
    }

    private function ensureDir(string $path): void
    {
        $dir = dirname(ltrim($path, '/'));
        if ($dir === '.' || $dir === '') {
            return;
        }
        $segments = explode('/', $dir);
        $built = '';
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            $built .= '/' . $segment;
            Http::request('MKCOL', $this->base() . $built, $this->headers(), null, 15);
        }
    }

    public function put(string $path, string $content): bool
    {
        if ($this->base() === '') {
            return false;
        }
        $this->ensureDir($path);
        $res = Http::request('PUT', $this->base() . '/' . ltrim($path, '/'), array_merge($this->headers(), [
            'Content-Type'   => 'application/octet-stream',
            'Content-Length' => (string) strlen($content),
        ]), $content, 60);
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    public function delete(string $path): bool
    {
        $res = Http::request('DELETE', $this->base() . '/' . ltrim($path, '/'), $this->headers());
        return $res['status'] >= 200 && $res['status'] < 300;
    }

    public function url(string $path): string
    {
        $domain = $this->customDomain();
        if ($domain !== '') {
            return rtrim($domain, '/') . '/' . ltrim($path, '/');
        }
        return $this->base() . '/' . ltrim($path, '/');
    }

    public function test(): array
    {
        if ($this->base() === '') {
            return $this->fail('未填写 WebDAV 地址');
        }
        $res = Http::request('PROPFIND', $this->base(), array_merge($this->headers(), ['Depth' => '0']), null, 15);
        if ($res['status'] === 207 || $res['status'] === 200) {
            return $this->ok('WebDAV 连接成功');
        }
        return $this->fail('连接失败，HTTP ' . $res['status']);
    }
}
