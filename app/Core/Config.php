<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Core;

/**
 * 配置访问层。
 *
 * 优先读合并后的 woniu 配置（defaults.php + installed.php），
 * 这就是 原有配置 + config/installed.php 结构；
 * 找不到的键再回落到 Laravel 自己的 config。
 */
class Config
{
    /** 运行时覆写，优先级最高 */
    private static array $overrides = [];

    public static function init(array $items): void
    {
        self::$overrides = $items;
    }

    public static function get(string $key, $default = null)
    {
        $value = self::lookup(self::$overrides, $key);
        if ($value !== null) {
            return $value;
        }

        $woniu = config('woniu');
        if (is_array($woniu)) {
            $value = self::lookup($woniu, $key);
            if ($value !== null) {
                return $value;
            }
        }

        $value = config($key);
        return $value === null ? $default : $value;
    }

    public static function set(string $key, $value): void
    {
        $segments = explode('.', $key);
        $ref = &self::$overrides;
        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }

    public static function all(): array
    {
        $woniu = config('woniu');
        return array_replace_recursive(is_array($woniu) ? $woniu : [], self::$overrides);
    }

    /**
     * 按点号路径取值，取不到返回 null（区分「值为 null」和「键不存在」）。
     */
    private static function lookup(array $items, string $key)
    {
        $value = $items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}
