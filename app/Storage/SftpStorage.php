<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Storage;

use App\Core\Http;

/**
 * SFTP 存储驱动。
 *
 * 零额外依赖：复用 curl 的 sftp:// 协议（需要 PHP curl 编译了 libssh2）。
 * 配置字段：host / port(22) / username / password / root / domain。
 */
class SftpStorage extends Driver
{
    private function supported(): bool
    {
        if (!extension_loaded('curl')) {
            return false;
        }
        $ver = curl_version();
        return in_array('sftp', $ver['protocols'] ?? [], true);
    }

    private function baseUrl(): string
    {
        $host = $this->conf('host');
        $port = $this->conf('port', '22');
        $user = rawurlencode($this->conf('username'));
        $pass = rawurlencode($this->conf('password'));
        $root = trim($this->conf('root', '/'), '/');
        $base = 'sftp://' . $user . ':' . $pass . '@' . $host . ':' . $port . '/';
        if ($root !== '') {
            $base .= $root . '/';
        }
        return $base;
    }

    public function put(string $path, string $content): bool
    {
        if (!$this->supported()) {
            return false;
        }
        $fp = fopen('php://temp', 'r+');
        if ($fp === false) {
            return false;
        }
        fwrite($fp, $content);
        rewind($fp);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl() . ltrim($path, '/'),
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILE => $fp,
            CURLOPT_INFILESIZE => strlen($content),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_FTP_CREATE_MISSING_DIRS => true,
        ]);
        curl_exec($ch);
        $ok = curl_errno($ch) === 0;
        curl_close($ch);
        fclose($fp);
        return $ok;
    }

    public function delete(string $path): bool
    {
        if (!$this->supported()) {
            return false;
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl() . ltrim($path, '/'),
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        curl_exec($ch);
        $ok = curl_errno($ch) === 0;
        curl_close($ch);
        return $ok;
    }

    public function url(string $path): string
    {
        $domain = $this->customDomain();
        return rtrim($domain, '/') . '/' . ltrim($path, '/');
    }

    public function test(): array
    {
        if (!$this->supported()) {
            return $this->fail('当前 PHP 的 curl 未启用 SFTP 支持（需要 libssh2），无法使用 SFTP 存储');
        }
        $probe = '.woniu_probe_' . time() . '.txt';
        if (!$this->put($probe, 'woniu probe')) {
            return $this->fail('SFTP 写入失败，请检查主机 / 端口 / 账号 / 根目录权限');
        }
        $this->delete($probe);
        return $this->ok('SFTP 连接成功');
    }
}
