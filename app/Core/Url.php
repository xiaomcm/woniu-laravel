<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Core;

/**
 * 站内地址生成。
 *
 *既有的 url() 与 asset() 都以 base_url() 为根：
 *   base_url() 优先取安装向导写入的 app.url，
 *   没写的话按当前请求的根路径算（兼容装在子目录的情况）。
 *
 * 这里把这套规则收敛成一处，Url 生成器与全局 helper 都走这里，
 * 不依赖函数定义的先后顺序。
 */
class Url
{
    /**
     * 站点根地址（带尾斜杠的形式不含）。
     */
    public static function base(): string
    {
        static $base = null;
        if ($base !== null) {
            return $base;
        }

        $configured = (string) Config::get('app.url', '');
        if ($configured !== '') {
            return $base = rtrim($configured, '/');
        }

        return $base = rtrim((string) request()->root(), '/');
    }

    /**
     * 站内地址。
     *
     * @param string      $path 站内路径
     * @param string|null $tail 额外参数（已编码好的路径片段）
     */
    public static function to(string $path, ?string $tail = null): string
    {
        $path = ltrim($path, '/');
        if ($tail !== null && $tail !== '') {
            $path = trim($path . '/' . $tail, '/');
        }
        return self::base() . '/' . $path;
    }
}
