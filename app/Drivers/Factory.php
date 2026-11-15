<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers;

class Factory
{
    public static function make(array $row): ?Driver
    {
        $type = (string) ($row['type'] ?? '');
        $provider = (string) ($row['provider'] ?? '');
        $meta = Registry::provider($type, $provider);
        if ($meta === null) {
            return null;
        }
        $config = json_decode((string) ($row['config'] ?? '{}'), true);
        $class = $meta['class'];
        return new $class(is_array($config) ? $config : []);
    }

    public static function supports(string $type, string $provider): bool
    {
        return Registry::provider($type, $provider) !== null;
    }

    /**
     * 后台表单默认值填充
     */
    public static function defaults(string $type, string $provider): array
    {
        $defaults = [];
        foreach (Registry::fields($type, $provider) as $field) {
            $defaults[$field['key']] = (string) ($field['default'] ?? '');
        }
        return $defaults;
    }

    public static function normalizeConfig(string $type, string $provider, array $input): array
    {
        $config = [];
        foreach (Registry::fields($type, $provider) as $field) {
            $key = $field['key'];
            $value = $input[$key] ?? '';
            $config[$key] = is_array($value) ? implode(',', $value) : trim((string) $value);
        }
        return $config;
    }
}
