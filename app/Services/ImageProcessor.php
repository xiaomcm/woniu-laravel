<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Services;

class ImageProcessor
{
    public const MIME_MAP = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'bmp'  => 'image/bmp',
        'webp' => 'image/webp',
        'ico'  => 'image/x-icon',
        'svg'  => 'image/svg+xml',
        'avif' => 'image/avif',
        'heic' => 'image/heic',
    ];

    public static function gdAvailable(): bool
    {
        return function_exists('imagecreatefromstring');
    }

    public static function processable(string $mime): bool
    {
        return self::gdAvailable() && in_array($mime, [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
        ], true);
    }

    public static function mimeFromExtension(string $extension): string
    {
        return self::MIME_MAP[strtolower($extension)] ?? 'application/octet-stream';
    }

    public static function extensionFromMime(string $mime): string
    {
        $flip = array_flip(self::MIME_MAP);
        return (string) ($flip[strtolower($mime)] ?? '');
    }



    public static function info(string $binary): ?array
    {
        $info = @getimagesizefromstring($binary);
        if (is_array($info) && ($info[0] ?? 0) > 0 && ($info[1] ?? 0) > 0) {
            return [
                'width'  => (int) $info[0],
                'height' => (int) $info[1],
                'mime'   => (string) ($info['mime'] ?? 'image/jpeg'),
            ];
        }
        return null;
    }



    public static function svgInfo(string $binary): array
    {
        $width = 0;
        $height = 0;
        if (preg_match('/<svg[^>]*width=["\']?([\d.]+)/i', $binary, $m)) {
            $width = (int) round((float) $m[1]);
        }
        if (preg_match('/<svg[^>]*height=["\']?([\d.]+)/i', $binary, $m)) {
            $height = (int) round((float) $m[1]);
        }
        if (($width === 0 || $height === 0) && preg_match('/viewBox=["\']([\d.\s\-]+)["\']/i', $binary, $m)) {
            $parts = preg_split('/\s+/', trim($m[1]));
            if (count($parts) === 4) {
                $width  = (int) round((float) $parts[2]);
                $height = (int) round((float) $parts[3]);
            }
        }
        return ['width' => $width, 'height' => $height, 'mime' => 'image/svg+xml'];
    }



    public static function optimize(string $binary, string $mime, int $maxWidth, int $quality): array
    {
        $info = self::info($binary) ?? ['width' => 0, 'height' => 0, 'mime' => $mime];
        $result = ['binary' => $binary, 'width' => $info['width'], 'height' => $info['height'], 'changed' => false];

        if (!self::processable($mime)) {
            return $result;
        }

        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return $result;
        }

        $image = self::applyOrientation($image, $mime);

        $width  = imagesx($image);
        $height = imagesy($image);

        if ($maxWidth > 0 && $width > $maxWidth) {
            $newHeight = max(1, (int) round($height * $maxWidth / $width));
            $scaled = imagescale($image, $maxWidth, $newHeight, IMG_BICUBIC);
            if ($scaled !== false) {
                imagedestroy($image);
                $image  = $scaled;
                $width  = imagesx($image);
                $height = imagesy($image);
            }
        }

        $encoded = self::encode($image, $mime, $quality);
        imagedestroy($image);

        if ($encoded === null) {
            return $result;
        }


        if (strlen($encoded) >= strlen($binary) && $maxWidth <= 0) {
            return $result;
        }
        return ['binary' => $encoded, 'width' => $width, 'height' => $height, 'changed' => true];
    }

    public static function watermark(string $binary, string $mime, array $options): string
    {
        if (!self::processable($mime)) {
            return $binary;
        }
        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return $binary;
        }

        $text = trim((string) ($options['text'] ?? ''));
        $imagePath = (string) ($options['image'] ?? '');
        $position = (string) ($options['position'] ?? 'bottom-right');
        $opacity = max(0, min(100, (int) ($options['opacity'] ?? 45)));
        $fontSize = max(8, (int) ($options['size'] ?? 18));

        $canvasWidth  = imagesx($image);
        $canvasHeight = imagesy($image);

        if ($text !== '') {
            $color = imagecolorallocatealpha($image, 255, 255, 255, (int) round(127 * (1 - $opacity / 100)));
            $shadow = imagecolorallocatealpha($image, 0, 0, 0, (int) round(127 * (1 - $opacity / 100)));
            $font = BASE_PATH . '/resources/fonts/watermark.ttf';

            if (is_file($font) && function_exists('imagettftext')) {
                $box = @imagettfbbox($fontSize, 0, $font, $text);
                $textWidth = $box === false ? $fontSize * mb_strlen($text) : abs($box[2] - $box[0]);
                $textHeight = $box === false ? $fontSize : abs($box[7] - $box[1]);
                [$x, $y] = self::position($position, $canvasWidth, $canvasHeight, (int) $textWidth, (int) $textHeight, 20);
                $baseY = $y + (int) $textHeight;
                @imagettftext($image, $fontSize, 0, $x + 1, $baseY + 1, $shadow, $font, $text);
                @imagettftext($image, $fontSize, 0, $x, $baseY, $color, $font, $text);
            } else {
                $font = 5;
                $textWidth = imagefontwidth($font) * strlen($text);
                $textHeight = imagefontheight($font);
                [$x, $y] = self::position($position, $canvasWidth, $canvasHeight, $textWidth, $textHeight, 12);
                imagestring($image, $font, $x + 1, $y + 1, $text, $shadow);
                imagestring($image, $font, $x, $y, $text, $color);
            }
        } elseif ($imagePath !== '' && is_file($imagePath)) {
            $mark = @imagecreatefromstring((string) file_get_contents($imagePath));
            if ($mark !== false) {
                $markWidth  = imagesx($mark);
                $markHeight = imagesy($mark);
                [$x, $y] = self::position($position, $canvasWidth, $canvasHeight, $markWidth, $markHeight, 20);
                imagecopymerge($image, $mark, $x, $y, 0, 0, $markWidth, $markHeight, $opacity);
                imagedestroy($mark);
            }
        }

        $encoded = self::encode($image, $mime, 92);
        imagedestroy($image);
        return $encoded ?? $binary;
    }



    public static function thumbnail(string $binary, string $mime, int $width): ?array
    {
        if (!self::processable($mime) || $width <= 0) {
            return null;
        }
        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return null;
        }
        $sourceWidth  = imagesx($image);
        $sourceHeight = imagesy($image);
        $targetWidth  = min($width, $sourceWidth);
        $targetHeight = max(1, (int) round($sourceHeight * $targetWidth / max(1, $sourceWidth)));

        $thumb = imagescale($image, $targetWidth, $targetHeight, IMG_BICUBIC);
        imagedestroy($image);
        if ($thumb === false) {
            return null;
        }


        $encoded = self::encode($thumb, 'image/jpeg', 82);
        imagedestroy($thumb);
        if ($encoded === null) {
            return null;
        }
        return ['binary' => $encoded, 'width' => $targetWidth, 'height' => $targetHeight];
    }

    /**
     * 生成固定尺寸的封面：不够大的图放大、比例不对的从中心裁掉
     * 广场卡片要的就是「所有图一样大」，所以这里不用等比缩放，直接 cover 到目标画布
     * @return array{binary:string,width:int,height:int}|null
     */
    public static function cover(string $binary, string $mime, int $width, int $height): ?array
    {
        if (!self::processable($mime) || $width <= 0 || $height <= 0) {
            return null;
        }
        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return null;
        }

        $image = self::resize($image, $width, $height, 'cover');
        // 质量 84：1280×860 大约 90–180KB，再高只是白给流量
        $encoded = self::encode($image, 'image/jpeg', 84);
        imagedestroy($image);
        if ($encoded === null) {
            return null;
        }

        return ['binary' => $encoded, 'width' => $width, 'height' => $height];
    }

    /**
     * 按 URL 参数实时处理图片：尺寸、裁剪方式、滤镜、旋转、质量、格式
     * @param array{width?:int,height?:int,fit?:string,quality?:int,format?:string,filter?:string,rotate?:int,flip?:string} $ops
     * @return array{binary:string,mime:string}|null
     */
    public static function transform(string $binary, string $mime, array $ops): ?array
    {
        if (!self::processable($mime) || $ops === []) {
            return null;
        }
        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return null;
        }

        $sw = imagesx($image);
        $sh = imagesy($image);

        $width  = max(0, (int) ($ops['width'] ?? 0));
        $height = max(0, (int) ($ops['height'] ?? 0));

        // 只给一边时按原比例补齐另一边，避免拉伸变形
        if ($width > 0 && $height === 0) {
            $height = max(1, (int) round($sh * $width / max(1, $sw)));
        } elseif ($height > 0 && $width === 0) {
            $width = max(1, (int) round($sw * $height / max(1, $sh)));
        }

        if ($width > 0 && $height > 0) {
            $image = self::resize($image, $width, $height, (string) ($ops['fit'] ?? 'cover'));
        }

        $rotate = (int) ($ops['rotate'] ?? 0);
        if ($rotate !== 0) {
            $rotated = imagerotate($image, (360 - ($rotate % 360)) % 360, 0);
            if ($rotated !== false) {
                imagedestroy($image);
                $image = $rotated;
            }
        }

        $flip = (string) ($ops['flip'] ?? '');
        if ($flip === 'x' || $flip === 'y') {
            imageflip($image, $flip === 'x' ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
        }

        $filter = (string) ($ops['filter'] ?? '');
        $filterMap = [
            'gray'    => IMG_FILTER_GRAYSCALE,
            'negate'  => IMG_FILTER_NEGATE,
            'bright'  => IMG_FILTER_BRIGHTNESS,
            'contrast' => IMG_FILTER_CONTRAST,
            'blur'    => IMG_FILTER_GAUSSIAN_BLUR,
            'edge'    => IMG_FILTER_EDGEDETECT,
            'emboss'  => IMG_FILTER_EMBOSS,
        ];
        if (isset($filterMap[$filter])) {
            $arg = match ($filter) {
                'bright'   => 30,
                'contrast' => -15,
                default    => 0,
            };
            imagefilter($image, $filterMap[$filter], $arg);
        } elseif ($filter === 'sepia') {
            imagefilter($image, IMG_FILTER_GRAYSCALE);
            imagefilter($image, IMG_FILTER_COLORIZE, 60, 30, -20);
        } elseif ($filter === 'sharpen') {
            $sharpen = [
                [0, -1, 0],
                [-1, 5, -1],
                [0, -1, 0],
            ];
            $sum = array_sum(array_map('array_sum', $sharpen));
            imageconvolution($image, $sharpen, $sum <= 0 ? 1 : $sum, 0);
        }

        $mime = $mime === 'image/jpg' ? 'image/jpeg' : $mime;
        $format = strtolower((string) ($ops['format'] ?? ''));
        $targetMime = match ($format) {
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            default => $mime,
        };
        if (!function_exists('imagewebp') && $targetMime === 'image/webp') {
            $targetMime = 'image/jpeg';
        }

        $quality = max(1, min(100, (int) ($ops['quality'] ?? 88)));
        $encoded = self::encode($image, $targetMime, $quality);
        imagedestroy($image);

        if ($encoded === null) {
            return null;
        }
        return ['binary' => $encoded, 'mime' => $targetMime];
    }

    /**
     * cover 居中裁剪，contain 内缩留白，stretch 直接拉伸
     */
    private static function resize($image, int $width, int $height, string $fit)
    {
        $sw = imagesx($image);
        $sh = imagesy($image);

        if ($fit === 'stretch') {
            $canvas = self::canvas($width, $height);
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, $sw, $sh);
            imagedestroy($image);
            return $canvas;
        }

        if ($fit === 'contain') {
            $scale = min($width / max(1, $sw), $height / max(1, $sh));
            $nw = max(1, (int) round($sw * $scale));
            $nh = max(1, (int) round($sh * $scale));
            $canvas = self::canvas($width, $height);
            $dx = (int) (($width - $nw) / 2);
            $dy = (int) (($height - $nh) / 2);
            imagecopyresampled($canvas, $image, $dx, $dy, 0, 0, $nw, $nh, $sw, $sh);
            imagedestroy($image);
            return $canvas;
        }

        // cover：先按短边放大，再按目标比例从中心裁切
        $scale = max($width / max(1, $sw), $height / max(1, $sh));
        $nw = max(1, (int) round($sw * $scale));
        $nh = max(1, (int) round($sh * $scale));

        $canvas = self::canvas($width, $height);
        $sx = (int) max(0, ($nw - $width) / 2);
        $sy = (int) max(0, ($nh - $height) / 2);

        $scaled = self::canvas($nw, $nh);
        imagecopyresampled($scaled, $image, 0, 0, 0, 0, $nw, $nh, $sw, $sh);
        imagedestroy($image);

        imagecopy($canvas, $scaled, 0, 0, $sx, $sy, $width, $height);
        imagedestroy($scaled);
        return $canvas;
    }

    private static function canvas(int $width, int $height)
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
        imagealphablending($canvas, true);
        return $canvas;
    }

    private static function position(string $position, int $canvasWidth, int $canvasHeight, int $width, int $height, int $padding): array
    {
        $x = $padding;
        $y = $canvasHeight - $height - $padding;
        switch ($position) {
            case 'top-left':
                $x = $padding;
                $y = $padding;
                break;
            case 'top-center':
                $x = (int) (($canvasWidth - $width) / 2);
                $y = $padding;
                break;
            case 'top-right':
                $x = $canvasWidth - $width - $padding;
                $y = $padding;
                break;
            case 'center':
                $x = (int) (($canvasWidth - $width) / 2);
                $y = (int) (($canvasHeight - $height) / 2);
                break;
            case 'bottom-left':
                $x = $padding;
                $y = $canvasHeight - $height - $padding;
                break;
            case 'bottom-center':
                $x = (int) (($canvasWidth - $width) / 2);
                $y = $canvasHeight - $height - $padding;
                break;
            case 'bottom-right':
            default:
                $x = $canvasWidth - $width - $padding;
                $y = $canvasHeight - $height - $padding;
                break;
        }
        return [max(0, $x), max(0, $y)];
    }

    private static function applyOrientation($image, string $mime)
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }
        return $image;
    }

    private static function encode($image, string $mime, int $quality)
    {
        ob_start();
        $ok = false;
        switch ($mime) {
            case 'image/png':
                imagesavealpha($image, true);
                $ok = imagepng($image, null, (int) max(0, min(9, (int) round((100 - $quality) / 10))));
                break;
            case 'image/webp':
                $ok = function_exists('imagewebp') && imagewebp($image, null, $quality);
                break;
            case 'image/gif':
                $ok = imagegif($image);
                break;
            case 'image/jpeg':
            default:
                $ok = imagejpeg($image, null, $quality);
                break;
        }
        $output = ob_get_clean();
        if (!$ok || !is_string($output) || $output === '') {
            return null;
        }
        return $output;
    }
}
