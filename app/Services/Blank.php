<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Services;

/**
 * 空白占位图：被打回、下架、删除的图不出内容，只出一块纯白
 *
 * 关键在于比例要跟原图一模一样 —— 占位图是 16:10、原图是 3:2 的话，
 * 瀑布流里这一格的高度就会和同列其他图错开，一眼看得出是拼出来的。
 * 所以按图片自身宽高先约分（1280×860 → 64×43），比例是精确的，
 * 再按需缩到 240px 以内落盘，几 KB 不到，浏览器放大成纯色没有任何损失。
 */
class Blank
{
    /** 落盘文件的最大边长，纯色图不需要更大 */
    private const MAX_SIDE = 240;

    /** 宽高未知时的兜底尺寸（4:3，多数相机的默认比例） */
    public const DEFAULT_W = 1200;
    public const DEFAULT_H = 900;

    /**
     * 占位图地址，宽高来自 images 表的原始尺寸
     */
    public static function url(int $width, int $height): string
    {
        [$w, $h] = self::size($width, $height);
        return url('blank/' . $w . 'x' . $h . '.jpg');
    }

    /**
     * 把任意宽高规整成「比例不变、边长有限」的一对整数
     * 复杂度 O(log n)，只做一次 gcd
     */
    public static function size(int $width, int $height): array
    {
        if ($width < 1 || $height < 1) {
            return [self::DEFAULT_W, self::DEFAULT_H];
        }

        // 先约分，比例精确到像素；1280×860 约成 64×43 后仍然是同一个比例
        $gcd = self::gcd($width, $height);
        $w = max(1, intdiv($width, $gcd));
        $h = max(1, intdiv($height, $gcd));

        // 约不掉（互质）的大尺寸再等比缩，误差不到 1 像素
        $long = max($w, $h);
        if ($long > self::MAX_SIDE) {
            $scale = self::MAX_SIDE / $long;
            $w = max(1, (int) round($w * $scale));
            $h = max(1, (int) round($h * $scale));
        }

        return [$w, $h];
    }

    /**
     * 取（或生成）纯白图片文件，失败返回 null 由调用方退回 SVG
     */
    public static function path(int $width, int $height): ?string
    {
        [$w, $h] = self::size($width, $height);
        $dir = self::cacheDir();
        $target = $dir . '/' . $w . 'x' . $h . '.jpg';
        if (is_file($target) && filesize($target) > 0) {
            return $target;
        }
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $canvas = @imagecreatetruecolor($w, $h);
        if ($canvas === false) {
            return null;
        }
        // 纯白：不掺主题色，深色主题下也照样是白块，和「以前那样」一致
        $white = (int) imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $w, $h, $white);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $ok = @imagejpeg($canvas, $target, 80);
        imagedestroy($canvas);

        return $ok ? $target : null;
    }

    public static function cacheDir(): string
    {
        return BASE_PATH . '/storage/cache/blank';
    }

    private static function gcd(int $a, int $b): int
    {
        $a = abs($a);
        $b = abs($b);
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }
        return max(1, $a);
    }
}
