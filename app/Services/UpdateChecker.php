<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 *
 * 新版本检查：每次打开后台都实时读 GitHub 最新版本号，只提示、不下发。
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

    /**
     * 实时检查：每次调用都去 GitHub 拉一次，不读缓存。
     * 好处是发版后一打开后台立刻能看到提示，不用等缓存过期。
     * 代价是每次开后台都会打一次 GitHub；连不上时靠 fetch() 的 4 秒短超时兜底并静默，
     * 不会把后台卡住，也不会显示半成品提示。
     *
     * 返回结构：
     *   ['has_update'=>bool, 'latest'=>string, 'current'=>string, 'url'=>string, 'ok'=>bool]
     * ok=false 表示这次没查到（网络问题 / 接口异常），调用方静默不显示。
     */
    public static function check(): array
    {
        $current = trim((string) Config::get('app.version', ''));
        $fresh = self::fetch();
        return self::decorate($fresh, $current);
    }

    /** 真正去 GitHub 拉一次版本号；任何异常都吞掉，返回「没查到」 */
    private static function fetch(): array
    {
        // 测试环境绝不连外网，避免测试变慢或依赖 GitHub 可用性
        try {
            if (\Illuminate\Support\Facades\App::environment('testing')) {
                return ['latest' => '', 'ok' => false];
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
                return ['latest' => '', 'ok' => false];
            }
            $data = json_decode($body, true);
            if (!is_array($data)) {
                return ['latest' => '', 'ok' => false];
            }
            // tag 形如 v2.0.4，去掉开头的 v 再比
            $tag = trim((string) ($data['tag_name'] ?? ''));
            $latest = ltrim($tag, 'vV');
            if ($latest === '' || !preg_match('/^\d+\.\d+\.\d+$/', $latest)) {
                return ['latest' => '', 'ok' => false];
            }
            return ['latest' => $latest, 'ok' => true];
        } catch (\Throwable $e) {
            return ['latest' => '', 'ok' => false];
        }
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
}
