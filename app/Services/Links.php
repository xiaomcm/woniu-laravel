<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Services;

class Links
{


    public static function of(array $image): array
    {
        $url = trim((string) ($image['url'] ?? ''));
        // url 兜底：历史数据里这一列可能是空的（早期版本导入的示例图），
        // 按 pathname 拼一个能用的地址，不然页面会渲染成 <img src="">（裂图）。
        if ($url === '') {
            $pathname = ltrim((string) ($image['pathname'] ?? ''), '/');
            if ($pathname !== '') {
                $url = image_public_url($pathname);
            }
        } else {
            // 对外不给 uploads 这一段
            $url = str_replace('/uploads/', '/', $url);
        }
        $name = (string) ($image['name'] ?? 'image');
        $thumb = trim((string) ($image['thumb_url'] ?? ''));
        // 小图不上缩略图，thumb_url 会是空的；回落到统一入口，那边会按需补生成
        if ($thumb === '') {
            $sha1 = trim((string) ($image['sha1'] ?? ''));
            if ($sha1 !== '') {
                $thumb = url('thumbs/' . substr($sha1, 0, 2) . '/' . $sha1 . '.jpg');
            }
        }
        return [
            'url'                  => $url,
            'html'                 => '<img src="' . $url . '" alt="' . htmlspecialchars($name, ENT_QUOTES) . '">',
            'bbcode'               => '[img]' . $url . '[/img]',
            'markdown'             => '![' . $name . '](' . $url . ')',
            'markdown_with_link'   => '[![' . $name . '](' . $thumb . ')](' . $url . ')',
            'thumbnail_url'        => $thumb,
            'delete_url'           => url('images/' . (int) ($image['id'] ?? 0)),
        ];
    }



    public static function apiPayload(array $image): array
    {
        return [
            'key'        => (string) ($image['sha1'] ?? ''),
            'id'         => (int) ($image['id'] ?? 0),
            'name'       => (string) ($image['name'] ?? ''),
            'pathname'   => (string) ($image['pathname'] ?? ''),
            'origin_name'=> (string) ($image['original_name'] ?? ''),
            'size'       => (int) ($image['size'] ?? 0),
            'mimetype'   => (string) ($image['mimetype'] ?? ''),
            'extension'  => (string) ($image['extension'] ?? ''),
            'md5'        => (string) ($image['md5'] ?? ''),
            'sha1'       => (string) ($image['sha1'] ?? ''),
            'width'      => (int) ($image['width'] ?? 0),
            'height'     => (int) ($image['height'] ?? 0),
            'status'     => (int) ($image['status'] ?? 1),
            'created_at' => (string) ($image['created_at'] ?? ''),
            'links'      => self::of($image),
        ];
    }
}
