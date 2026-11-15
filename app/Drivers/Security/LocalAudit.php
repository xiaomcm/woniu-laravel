<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers\Security;

use App\Drivers\Result;
use App\Drivers\SecurityDriver;

class LocalAudit extends SecurityDriver
{
    public function name(): string
    {
        return '本地敏感词';
    }

    public function audit(array $image): Result
    {
        $filename = (string) ($image['name'] ?? '');
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        $blockExt = $this->split((string) $this->cfg('block_ext', ''));
        if ($ext !== '' && in_array($ext, $blockExt, true)) {
            return Result::fail('该文件类型不允许上传');
        }

        $haystack = mb_strtolower($filename);
        foreach ($this->split((string) $this->cfg('words', '')) as $word) {
            if ($word !== '' && mb_strpos($haystack, $word) !== false) {
                return Result::fail('文件名包含违规词：' . $word);
            }
        }

        $width = (int) ($image['width'] ?? 0);
        $height = (int) ($image['height'] ?? 0);

        $minWidth = $this->int('min_width', 0);
        if ($minWidth > 0 && $width > 0 && $width < $minWidth) {
            return Result::fail('图片宽度不足 ' . $minWidth . ' 像素');
        }
        $minHeight = $this->int('min_height', 0);
        if ($minHeight > 0 && $height > 0 && $height < $minHeight) {
            return Result::fail('图片高度不足 ' . $minHeight . ' 像素');
        }

        $maxRatio = (float) $this->cfg('max_ratio', 0);
        if ($maxRatio > 0 && $width > 0 && $height > 0) {
            $ratio = $width / $height;
            if ($ratio > $maxRatio || $ratio < 1 / $maxRatio) {
                return Result::fail('图片宽高比超出允许范围');
            }
        }

        return Result::pass('本地校验通过');
    }

    private function split(string $raw): array
    {
        $parts = preg_split('/[\s,，、;；]+/u', $raw) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn ($v) => $v !== ''));
    }
}
