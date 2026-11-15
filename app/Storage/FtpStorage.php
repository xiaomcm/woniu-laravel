<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Storage;

class FtpStorage extends Driver
{
    private function connect()
    {
        if (!function_exists('ftp_connect')) {
            return null;
        }
        $host = $this->conf('host');
        $port = (int) $this->conf('port', '21');
        $ssl = !empty($this->config['ssl']);
        $conn = $ssl ? @ftp_ssl_connect($host, $port, 15) : @ftp_connect($host, $port, 15);
        if ($conn === false) {
            return null;
        }
        if (!@ftp_login($conn, $this->conf('username'), $this->conf('password'))) {
            @ftp_close($conn);
            return null;
        }
        @ftp_pasv($conn, true);
        $root = trim($this->conf('root', '/'), '/');
        if ($root !== '') {
            @ftp_chdir($conn, $root);
        }
        return $conn;
    }

    public function put(string $path, string $content): bool
    {
        $conn = $this->connect();
        if ($conn === null) {
            return false;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'woniu');
        if ($tmp === false) {
            ftp_close($conn);
            return false;
        }
        file_put_contents($tmp, $content);
        $target = ltrim($path, '/');
        $dir = dirname($target);
        $this->mkdirRecursive($conn, $dir);

        $ok = @ftp_put($conn, $target, $tmp, FTP_BINARY);
        @unlink($tmp);
        @ftp_close($conn);
        return (bool) $ok;
    }

    private function mkdirRecursive($conn, string $dir): void
    {
        if ($dir === '.' || $dir === '') {
            return;
        }
        $parts = explode('/', str_replace('\\', '/', $dir));
        $current = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $current .= ($current === '' ? '' : '/') . $part;
            if (!@ftp_chdir($conn, $current)) {
                @ftp_mkdir($conn, $current);
                @ftp_chdir($conn, $current);
            }
        }
        @ftp_chdir($conn, '/');
        $root = trim($this->conf('root', '/'), '/');
        if ($root !== '') {
            @ftp_chdir($conn, $root);
        }
    }

    public function delete(string $path): bool
    {
        $conn = $this->connect();
        if ($conn === null) {
            return false;
        }
        $ok = @ftp_delete($conn, ltrim($path, '/'));
        @ftp_close($conn);
        return (bool) $ok;
    }

    public function url(string $path): string
    {
        $domain = $this->customDomain();
        return rtrim($domain, '/') . '/' . ltrim($path, '/');
    }

    public function test(): array
    {
        if (!function_exists('ftp_connect')) {
            return $this->fail('PHP 未安装 ftp 扩展');
        }
        $conn = $this->connect();
        if ($conn === null) {
            return $this->fail('无法连接或登录 FTP');
        }
        @ftp_close($conn);
        return $this->ok('FTP 连接成功');
    }
}
