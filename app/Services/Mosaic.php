<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Services;

/**
 * 加密图片的公开缩略图：先压成几十像素的小图，再用最近邻放大回来看成色块
 * 由服务端生成并落盘，浏览器拿到的只有马赛克，原图细节不出站
 */
class Mosaic
{
    /** 缩到多少像素宽（值越小块越大） */
    private const BLOCKS = 24;

    /** 马赛克输出宽度 */
    private const OUTPUT_WIDTH = 720;

    public static function cacheDir(): string
    {
        return BASE_PATH . '/storage/cache/mosaic';
    }

    /**
     * 生成马赛克文件，已存在直接复用
     * $outWidth / $outHeight 可指定输出尺寸（广场封面要 1280×860），留空按 720 宽等比
     * 复杂度 O(像素数)，只有首次请求会走 GD
     */
    public static function ensure(string $source, string $key, int $outWidth = 0, int $outHeight = 0): ?string
    {
        $dir = self::cacheDir();
        $cleanKey = preg_replace('/[^a-f0-9]/i', '', $key);
        if ($cleanKey === '') {
            return null;
        }
        // 文件名带块参数与输出尺寸，调整参数后旧缓存自然失效
        $outWidth = $outWidth > 0 ? $outWidth : self::OUTPUT_WIDTH;
        $suffix = $outHeight > 0 ? $outWidth . 'x' . $outHeight : (string) $outWidth;
        $target = $dir . '/' . $cleanKey . '-' . self::BLOCKS . '-' . $suffix . '.jpg';
        if (is_file($target) && filesize($target) > 0) {
            return $target;
        }
        if (!is_file($source)) {
            return null;
        }
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $raw = @file_get_contents($source);
        if ($raw === false) {
            return null;
        }
        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 1 || $height < 1) {
            imagedestroy($image);
            return null;
        }

        // 第一步：缩到 BLOCKS 像素宽，细节在这一步就没了
        $thumbHeight = max(1, (int) round($height * self::BLOCKS / $width));
        $small = imagescale($image, self::BLOCKS, $thumbHeight, IMG_BILINEAR_FIXED);
        imagedestroy($image);
        if ($small === false) {
            return null;
        }

        // 第二步：最近邻放大，保留硬边色块而不是模糊
        $outWidth = $outWidth > 0 ? $outWidth : self::OUTPUT_WIDTH;
        $outHeight = $outHeight > 0 ? $outHeight : max(1, (int) round($thumbHeight * $outWidth / self::BLOCKS));
        $out = imagescale($small, $outWidth, $outHeight, IMG_NEAREST_NEIGHBOUR);
        imagedestroy($small);
        if ($out === false) {
            return null;
        }

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $ok = @imagejpeg($out, $target, 72);
        imagedestroy($out);
        if (!$ok) {
            return null;
        }
        return $target;
    }
}
