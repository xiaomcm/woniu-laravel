<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers;

abstract class Driver
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function cfg(string $key, mixed $default = ''): mixed
    {
        $value = $this->config[$key] ?? null;
        return ($value === null || $value === '') ? $default : $value;
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->cfg($key, $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->cfg($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }

    abstract public function name(): string;

    public function type(): string
    {
        return 'base';
    }
}
