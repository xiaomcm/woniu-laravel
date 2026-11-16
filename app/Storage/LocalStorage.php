<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Storage;

class LocalStorage extends Driver
{
    public function root(): string
    {
        $root = $this->conf('root', 'storage/uploads');
        if (!preg_match('#^([a-zA-Z]:|/|\\\\)#', $root)) {
            $root = BASE_PATH . '/' . trim($root, '/\\');
        }
        return rtrim($root, '/\\');
    }

    public function put(string $path, string $content): bool
    {
        $full = $this->root() . '/' . ltrim($path, '/');
        $dir = dirname($full);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
        return @file_put_contents($full, $content) !== false;
    }

    public function delete(string $path): bool
    {
        $full = $this->root() . '/' . ltrim($path, '/');
        return is_file($full) ? @unlink($full) : true;
    }

    public function url(string $path): string
    {
        $domain = $this->customDomain();
        if ($domain !== '') {
            return $domain . '/' . ltrim($path, '/');
        }
        // 不带 uploads 前缀，对外给的是短地址
        return image_public_url($path);
    }

    public function test(): array
    {
        $root = $this->root();
        if (!is_dir($root) && !@mkdir($root, 0755, true)) {
            return $this->fail('目录不存在且创建失败：' . $root);
        }
        if (!is_writable($root)) {
            return $this->fail('目录不可写：' . $root);
        }
        $probe = $root . '/.probe';
        if (@file_put_contents($probe, 'ok') === false) {
            return $this->fail('写入测试文件失败');
        }
        @unlink($probe);
        return $this->ok('本地目录可读写：' . $root);
    }
}
