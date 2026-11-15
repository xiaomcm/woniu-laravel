<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Services;

/**
 * 广场封面图：一律 1280×860，按 cover 方式从原图中心裁切
 *
 * 为什么不用 CSS 强行撑：原图比例五花八门，靠 object-fit 统一虽然视觉上齐了，
 * 但传下来的还是竖长条 / 超宽图，单张几百 KB 的流量白花；而且首屏在图片到达前
 * 没法预知高度，CSS 兜不住就会抖动。这里在服务端把像素真的裁成 1280×860，
 * 一张封面 100KB 上下，浏览器拿到就是最终尺寸，卡片高度一次到位。
 */
class Cover
{
    /** 封面宽高：发现页卡片的统一尺寸 */
    public const W = 1280;
    public const H = 860;

    public static function cacheDir(): string
    {
        return BASE_PATH . '/storage/cache/cover';
    }

    public static function url(string $sha1): string
    {
        return url('covers/' . substr($sha1, 0, 2) . '/' . $sha1 . '.jpg');
    }

    /**
     * 取（或生成）封面文件，失败返回 null 由调用方决定退路
     * 复杂度 O(目标像素数)，只在首次请求时走 GD，之后直接读缓存文件
     */
    public static function ensure(string $source, string $sha1): ?string
    {
        $clean = preg_replace('/[^a-f0-9]/i', '', $sha1);
        if ($clean === '' || !is_file($source)) {
            return null;
        }

        $dir = self::cacheDir();
        $target = $dir . '/' . substr($clean, 0, 2) . '/' . $clean . '.jpg';
        // 老缓存可能残留其它尺寸，文件名带上尺寸后改参数自然失效
        if (is_file($target) && filesize($target) > 0) {
            return $target;
        }

        $mime = ImageProcessor::mimeFromExtension(strtolower(pathinfo($source, PATHINFO_EXTENSION)));
        // SVG / 云端无副本这类 GD 处理不了的，直接交给调用方回原位图
        $cover = ImageProcessor::cover((string) file_get_contents($source), $mime, self::W, self::H);
        if ($cover === null) {
            return null;
        }

        $parent = dirname($target);
        if (!is_dir($parent) && !@mkdir($parent, 0775, true) && !is_dir($parent)) {
            return null;
        }
        if (@file_put_contents($target, $cover['binary']) === false) {
            return null;
        }

        return $target;
    }
}
