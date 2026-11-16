<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Storage;

abstract class Driver
{
    protected array $storage;
    protected array $config;

    public function __construct(array $storage)
    {
        $this->storage = $storage;
        $this->config  = is_array($storage['config_array'] ?? null)
            ? $storage['config_array']
            : (array) json_decode((string) ($storage['config'] ?? ''), true);
    }

    abstract public function put(string $path, string $content): bool;

    abstract public function delete(string $path): bool;

    abstract public function url(string $path): string;



    abstract public function test(): array;

    public function name(): string
    {
        return (string) ($this->storage['name'] ?? '');
    }

    public function id(): int
    {
        return (int) ($this->storage['id'] ?? 0);
    }

    protected function customDomain(): string
    {
        return rtrim(trim((string) ($this->config['domain'] ?? $this->storage['url'] ?? '')), '/');
    }

    protected function conf(string $key, string $default = ''): string
    {
        $value = $this->config[$key] ?? $default;
        return is_scalar($value) ? (string) $value : $default;
    }

    protected function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }

    protected function ok(string $message): array
    {
        return ['ok' => true, 'message' => $message];
    }
}
