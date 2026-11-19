<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Http;
use Illuminate\Support\Facades\Cache;

/**
 * 登录日志用的两项派生信息：设备简称、IP 归属地。
 *
 * 设备纯离线从 UA 解析，不依赖任何服务。
 * 地区：内网 IP 直接标本地；公网 IP 查免费归属地接口，结果缓存 24 小时，
 * 查询失败（没外网 / 超时 / 接口限流）一律静默回落「未知地区」，绝不影响登录。
 */
final class LoginContext
{
    /** 把 User-Agent 压成一句人话，例如「Windows · Chrome」 */
    public static function device(string $ua): string
    {
        $ua = trim($ua);
        if ($ua === '') {
            return '未知设备';
        }
        $os = '其他系统';
        if (stripos($ua, 'Windows') !== false) { $os = 'Windows'; }
        elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            $os = (stripos($ua, 'iPad') !== false || stripos($ua, 'Tablet') !== false) ? 'iPad' : 'iPhone';
        } elseif (stripos($ua, 'Android') !== false) { $os = 'Android'; }
        elseif (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) { $os = 'macOS'; }
        elseif (stripos($ua, 'Linux') !== false) { $os = 'Linux'; }

        $browser = '';
        if (stripos($ua, 'MicroMessenger') !== false) { $browser = '微信'; }
        elseif (stripos($ua, 'QQ/') !== false) { $browser = 'QQ'; }
        elseif (preg_match('/Edg\//i', $ua)) { $browser = 'Edge'; }
        elseif (preg_match('/OPR\//i', $ua) || stripos($ua, 'Opera') !== false) { $browser = 'Opera'; }
        elseif (stripos($ua, 'Firefox') !== false) { $browser = 'Firefox'; }
        elseif (stripos($ua, 'Chrome') !== false) { $browser = 'Chrome'; }
        elseif (stripos($ua, 'Safari') !== false) { $browser = 'Safari'; }
        elseif (stripos($ua, 'MSIE') !== false || stripos($ua, 'Trident') !== false) { $browser = 'IE'; }

        return $browser === '' ? $os : $os . ' · ' . $browser;
    }

    /** IP 归属地：本地网络 / 具体地区 / 未知地区 */
    public static function location(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return '未知地区';
        }
        if (self::isPrivate($ip)) {
            return '本地网络';
        }

        // 缓存 24 小时：同一 IP 反复登录不会每次都打外部接口
        $key = 'woniu_iploc_' . md5($ip);
        try {
            $cached = Cache::get($key);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        } catch (\Throwable $e) {
            // 缓存不可用时直接查，不阻断
        }

        $result = '未知地区';
        try {
            $url = 'http://ip-api.com/json/' . rawurlencode($ip)
                . '?lang=zh-CN&fields=status,country,regionName,city';
            $resp = Http::request('GET', $url, ['Accept' => 'application/json'], null, 2, 1);
            if ($resp['status'] === 200 && $resp['body'] !== '') {
                $data = json_decode($resp['body'], true);
                if (is_array($data) && ($data['status'] ?? '') === 'success') {
                    $parts = array_filter([
                        (string) ($data['country'] ?? ''),
                        (string) ($data['regionName'] ?? ''),
                        (string) ($data['city'] ?? ''),
                    ], static fn ($v) => $v !== '' && $v !== '-');
                    // 省和市重名时只留一个（直辖市常见「北京市 北京」）
                    $parts = array_values(array_unique($parts));
                    if ($parts !== []) {
                        $result = implode(' ', $parts);
                    }
                }
            }
        } catch (\Throwable $e) {
            // 外部查询失败不影响登录，保留「未知地区」
        }

        try {
            Cache::put($key, $result, 1440);
        } catch (\Throwable $e) {
        }
        return $result;
    }

    private static function isPrivate(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv6：回环与唯一本地地址
            return strcasecmp($ip, '::1') === 0 || stripos($ip, 'fc') === 0 || stripos($ip, 'fd') === 0;
        }
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
