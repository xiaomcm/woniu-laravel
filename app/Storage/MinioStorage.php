<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Storage;

/**
 * MinIO 存储驱动（S3 兼容，原生实现）。
 *
 * MinIO 本质上就是 S3 协议 + 自托管 Endpoint + path-style 寻址，
 * 因此直接继承 S3Storage，仅覆盖 Endpoint 默认值与寻址方式。
 * 配置字段：endpoint / key / secret / bucket / region(默认 us-east-1) / domain。
 */
class MinioStorage extends S3Storage
{
    public function name(): string
    {
        return 'MinIO';
    }

    protected function endpointParts(): array
    {
        $endpoint = $this->conf('endpoint', 'http://localhost:9000');
        if (!preg_match('#^https?://#i', $endpoint)) {
            $endpoint = 'http://' . $endpoint;
        }
        $parsed = parse_url($endpoint);
        return [
            'scheme' => $parsed['scheme'] ?? 'http',
            'host'   => $parsed['host'] ?? '',
            'port'   => isset($parsed['port']) ? ':' . $parsed['port'] : '',
            'path'   => rtrim($parsed['path'] ?? '', '/'),
        ];
    }

    protected function pathStyle(): bool
    {
        // MinIO 必须使用 path-style 寻址
        return true;
    }
}
