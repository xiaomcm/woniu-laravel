<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Core;

use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\UploadedFile;

/**
 * 请求访问层。
 *
 *原先直接读超全局变量，2.0 统一走 Laravel 的 Request 实例，
 * 但对外的静态 API 一个字没改 —— 业务代码里的 Request::int() / Request::input()
 * 全部照旧能用，行为也照旧。
 */
class Request
{
    private static ?array $jsonCache = null;

    public static function instance(): HttpRequest
    {
        return app('request');
    }

    public static function raw(): string
    {
        $raw = static::instance()->getContent();
        return is_string($raw) ? $raw : '';
    }

    public static function method(): string
    {
        $request = static::instance();
        $method = strtoupper($request->getMethod());
        //原先支持用表单里的 _method 覆写请求方法，Laravel 默认只认 X-HTTP-Method-Override 头
        if ($method === 'POST') {
            $override = strtoupper((string) $request->input('_method', ''));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }
        return $method;
    }

    public static function path(): string
    {
        $path = trim(static::instance()->path(), '/');
        return $path === '' ? '/' : '/' . $path;
    }

    public static function isPost(): bool
    {
        return static::method() === 'POST';
    }

    public static function isAjax(): bool
    {
        $request = static::instance();
        if (strtolower((string) $request->header('X-Requested-With', '')) === 'xmlhttprequest') {
            return true;
        }
        return str_contains(strtolower((string) $request->header('Accept', '')), 'application/json');
    }

    public static function header(string $name): ?string
    {
        $value = static::instance()->header($name);
        return $value === null ? null : (string) $value;
    }

    public static function bearer(): ?string
    {
        $token = static::instance()->bearerToken();
        if (is_string($token) && $token !== '') {
            return $token;
        }
        $query = static::instance()->query('token');
        return is_string($query) && $query !== '' ? $query : null;
    }

    public static function json(): array
    {
        if (self::$jsonCache !== null) {
            return self::$jsonCache;
        }
        self::$jsonCache = [];

        $request = static::instance();
        $contentType = strtolower((string) $request->header('Content-Type', ''));
        $raw = (string) $request->getContent();
        //既有的判定：带 json 的 content-type，或者请求体以 { 开头，都当 JSON 解析
        if ($raw !== '' && (str_contains($contentType, 'json') || str_starts_with(ltrim($raw), '{'))) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                self::$jsonCache = $decoded;
            }
        }
        return self::$jsonCache;
    }

    public static function all(): array
    {
        $request = static::instance();
        return array_merge($request->query->all(), $request->post(), static::json());
    }

    public static function input(string $key, $default = null)
    {
        $all = static::all();
        if (!array_key_exists($key, $all)) {
            return $default;
        }
        $value = $all[$key];
        return is_string($value) ? trim($value) : $value;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = static::input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = static::input($key, null);
        if ($value === null) {
            return $default;
        }
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }

    public static function file(string $key): ?array
    {
        $files = static::files($key);
        return $files[0] ?? null;
    }

    /**
     * 上传文件列表。保持既有的数组结构（name/type/tmp_name/error/size），
     * 上传服务那边一个字都不用改。
     */
    public static function files(string ...$keys): array
    {
        $request = static::instance();
        $result = [];
        foreach ($keys as $key) {
            $files = $request->file($key);
            if ($files === null) {
                continue;
            }
            if (!is_array($files)) {
                $files = [$files];
            }
            foreach ($files as $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }
                $result[] = [
                    'name'     => $file->getClientOriginalName(),
                    'type'     => $file->getClientMimeType(),
                    'tmp_name' => $file->getPathname(),
                    'error'    => $file->getError(),
                    'size'     => $file->getSize(),
                ];
            }
        }
        return $result;
    }

    public static function ip(): string
    {
        $server = static::instance()->server;
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            $value = $server->get($key);
            if (!empty($value)) {
                $parts = explode(',', (string) $value);
                $ip = trim($parts[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    public static function ua(): string
    {
        return substr((string) static::instance()->userAgent(), 0, 255);
    }

    public static function uri(): string
    {
        return (string) static::instance()->getRequestUri();
    }
}
