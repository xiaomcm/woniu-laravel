<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers\Security;

use App\Core\Http;
use App\Drivers\Result;
use App\Drivers\SecurityDriver;

/**
 * NSFW.js 自建服务审核。
 * POST {"url":"..."} 到自建服务，解析返回的 predictions 数组，
 * 对 Porn / Hentai / Sexy 等类别按概率阈值拦截。
 */
class NsfwJs extends SecurityDriver
{
    public function name(): string
    {
        return 'NSFW.js 自建';
    }

    public function audit(array $image): Result
    {
        $endpoint = (string) $this->cfg('endpoint');
        if ($endpoint === '') {
            return Result::fail('NSFW.js 接口地址未配置');
        }

        $imageUrl = (string) ($image['url'] ?? '');
        if ($imageUrl === '') {
            return Result::pass('无外链可审，跳过');
        }

        $payload = json_encode(['url' => $imageUrl], JSON_UNESCAPED_SLASHES);
        $response = Http::request('POST', $endpoint, ['Content-Type' => 'application/json'], $payload, 8);

        if ($response['error'] !== '' || $response['status'] === 0) {
            return Result::pass('NSFW 服务不可达，按策略放行：' . $response['error']);
        }
        $json = json_decode($response['body'], true);
        if (!is_array($json)) {
            return Result::pass('NSFW 返回无法解析，按策略放行');
        }

        $predictions = $json['predictions'] ?? $json;
        if (!is_array($predictions)) {
            return Result::pass('NSFW 预测结果格式异常，按策略放行');
        }

        $threshold = (float) $this->cfg('threshold', '0.7');
        $blockClasses = array_filter(array_map('trim', explode(',', $this->cfg('block_classes', 'Porn,Hentai,Sexy'))));

        foreach ($predictions as $p) {
            $cls = (string) ($p['className'] ?? '');
            $prob = (float) ($p['probability'] ?? 0);
            if ($cls !== '' && in_array($cls, $blockClasses, true) && $prob >= $threshold) {
                return Result::fail('NSFW 命中：' . $cls . '（' . round($prob * 100) . '%）', $predictions);
            }
        }

        return Result::pass('NSFW 检查通过', $predictions);
    }
}
