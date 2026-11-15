<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers\Cloud;

use App\Drivers\CloudDriver;

class TemplateUrl extends CloudDriver
{
    public function name(): string
    {
        return 'URL 模板云处理';
    }

    public function buildUrl(string $url, array $ops): string
    {
        $template = (string) $this->cfg('template', '{url}?w={width}&q={quality}');
        if ($template === '') {
            return $url;
        }

        $width = (int) ($ops['width'] ?? 0);
        $height = (int) ($ops['height'] ?? 0);
        $quality = (int) ($ops['quality'] ?? 85);
        $format = (string) ($ops['format'] ?? '');
        $blur = (int) ($ops['blur'] ?? 0);
        $sharpen = (int) ($ops['sharpen'] ?? 0);

        return strtr($template, [
            '{url}'     => $url,
            '{width}'   => (string) max(1, $width),
            '{height}'  => (string) max(1, $height),
            '{quality}' => (string) min(100, max(1, $quality)),
            '{format}'  => $format !== '' ? $format : 'jpg',
            '{blur}'    => (string) max(0, $blur),
            '{sharpen}' => (string) max(0, $sharpen),
        ]);
    }
}
