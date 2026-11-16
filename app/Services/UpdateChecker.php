<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 *
 * 新版本检查：只读 GitHub 的最新版本号，在后台提示「发现新版本」。
 * 不下发、不下载、不安装任何更新文件 —— 更新仍由站长手动覆盖（见 tools/update.php）。
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Http;

class UpdateChecker
{
    /** GitHub Releases 最新一版的接口，只取 tag_name，不碰 assets */
    private const API = 'https://api.github.com/repos/xiaomcm/woniu-laravel/releases/latest';
    private const RELEASES = 'https://github.com/xiaomcm/woniu-laravel/releases';

    private const CACHE_REL = '/storage/cache/update_check.json';
    /** 查到结果后缓存 6 小时，避免每次开后台都打 GitHub */
    private const TTL_OK = 21600;
    /** 查失败（含国内连不上 GitHub）只缓存 15 分钟，避免反复卡后台 */
    private const TTL_FAIL = 900;

    /**
     * 返回结构：
     *   ['has_update'=>bool, 'latest'=>string, 'current'=>string, 'url'=>string, 'ok'=>bool]
     * ok=false 表示这次没查到（网络问题 / 接口异常），调用方应静默不显示。
     */
    public static function check(): array
    {
        $current = trim((string) Config::get('app.version', ''));
        $cache = BASE_PATH . self::CACHE_REL;

        $cached = self::readCache($cache);
        if ($cached !== null && (time() - (int) ($cached['checked_at'] ?? 0)) < self::ttl($cached)) {
            return self::decorate($cached, $current);
        }

        $fresh = self::fetch();
        self::writeCache($cache, $fresh);
        return self::decorate($fresh, $current);
    }

    /** 真正去 GitHub 拉一次版本号；任何异常都吞掉，返回「没查到」 */
    private static function fetch(): array
    {
        // 测试环境绝不连外网，避免测试变慢或依赖 GitHub 可用性
        try {
            if (\Illuminate\Support\Facades\App::environment('testing')) {
                return ['checked_at' => time(), 'latest' => '', 'ok' => false];
            }
        } catch (\Throwable $e) {
            // 容器还没起来时按非测试环境处理，下面的请求自带超时不会挂住
        }

        try {
            // 连接和总超时都给 4 秒：国内连 GitHub 经常慢，不能把后台卡住
            $res = Http::request('GET', self::API, [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'woniu-image-host',
            ], null, 4, 4);
            $status = (int) ($res['status'] ?? 0);
            $body = trim((string) ($res['body'] ?? ''));
            if ($status < 200 || $status >= 300 || $body === '') {
                return ['checked_at' => time(), 'latest' => '', 'ok' => false];
            }
            $data = json_decode($body, true);
            if (!is_array($data)) {
                return ['checked_at' => time(), 'latest' => '', 'ok' => false];
            }
            // tag 形如 v2.0.4，去掉开头的 v 再比
            $tag = trim((string) ($data['tag_name'] ?? ''));
            $latest = ltrim($tag, 'vV');
            if ($latest === '' || !preg_match('/^\d+\.\d+\.\d+$/', $latest)) {
                return ['checked_at' => time(), 'latest' => '', 'ok' => false];
            }
            return ['checked_at' => time(), 'latest' => $latest, 'ok' => true];
        } catch (\Throwable $e) {
            return ['checked_at' => time(), 'latest' => '', 'ok' => false];
        }
    }

    /** 根据缓存里的 ok 决定保留多久 */
    private static function ttl(array $item): int
    {
        return !empty($item['ok']) ? self::TTL_OK : self::TTL_FAIL;
    }

    /** 拼出给视图用的结果，has_update 由版本号大小决定 */
    private static function decorate(array $item, string $current): array
    {
        $latest = (string) ($item['latest'] ?? '');
        $ok = !empty($item['ok']) && $latest !== '';
        return [
            'has_update' => $ok && $current !== '' && version_compare($latest, $current, '>'),
            'latest'     => $latest,
            'current'    => $current,
            'url'        => self::RELEASES,
            'ok'         => $ok,
        ];
    }

    private static function readCache(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /** 先写临时文件再 rename，避免并发请求把缓存写成半截 JSON */
    private static function writeCache(string $file, array $item): void
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        $tmp = $file . '.tmp-' . getmypid();
        if (@file_put_contents($tmp, json_encode($item, JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            return;
        }
        @rename($tmp, $file);
    }
}
