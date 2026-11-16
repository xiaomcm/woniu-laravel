<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Services\Links;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 图片直链地址契约。
 *
 * 对外给出去的图片地址不带 uploads 前缀：
 *   https://站点/2025/09/07/xxx.jpg
 * 而不是
 *   https://站点/uploads/2025/09/07/xxx.jpg
 *
 * 老地址必须继续能开（已经发出去的链接不能失效），所以两套路由都要在。
 * 这条测试锁住这两件事，避免有人改回带 uploads 的写法，或者把短地址路由删了。
 */
class ImageUrlSchemeContractTest extends TestCase
{
    private const PATHNAME = '2025/09/07/36e7017fd85f0280369217df71f35822a8e66f8b.jpg';

    public function test_helper_returns_url_without_uploads_prefix(): void
    {
        $url = image_public_url(self::PATHNAME);

        $this->assertStringNotContainsString('/uploads/', $url, '对外直链不应带 uploads');
        $this->assertStringEndsWith('/' . self::PATHNAME, $url, '直链应按 pathname 拼出来');
    }

    public function test_stored_absolute_url_is_rewritten_to_short_form(): void
    {
        // 老数据里 url 列存的是 .../uploads/2025/...，取出来要给短地址
        $row = Image::cast([
            'id' => 1,
            'user_id' => 1,
            'album_id' => null,
            'storage_id' => 1,
            'size' => 100,
            'width' => 10,
            'height' => 10,
            'status' => 1,
            'is_public' => 1,
            'views' => 0,
            'url' => url('uploads/' . self::PATHNAME),
            'pathname' => self::PATHNAME,
            'thumb_url' => '',
            'name' => 'x.jpg',
        ]);

        $this->assertStringNotContainsString('/uploads/', (string) $row['url'], '老地址取出来也要给短地址');
        $this->assertStringEndsWith('/' . self::PATHNAME, (string) $row['url']);
    }

    public function test_empty_url_falls_back_to_short_form(): void
    {
        $row = Image::cast([
            'id' => 1,
            'user_id' => 1,
            'album_id' => null,
            'storage_id' => 1,
            'size' => 100,
            'width' => 10,
            'height' => 10,
            'status' => 1,
            'is_public' => 1,
            'views' => 0,
            'url' => '',
            'pathname' => self::PATHNAME,
            'thumb_url' => '',
            'name' => 'x.jpg',
        ]);

        $this->assertStringNotContainsString('/uploads/', (string) $row['url']);
        $this->assertStringEndsWith('/' . self::PATHNAME, (string) $row['url']);
    }

    public function test_share_links_use_short_form(): void
    {
        $links = Links::of([
            'url' => '',
            'pathname' => self::PATHNAME,
            'name' => 'x.jpg',
            'sha1' => '36e7017fd85f0280369217df71f35822a8e66f8b',
            'thumb_url' => '',
        ]);

        $flat = json_encode($links, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('/uploads/', (string) $flat, '分享链接也不该带 uploads');
    }

    public function test_short_route_exists_and_old_route_is_kept(): void
    {
        $uris = [];
        foreach (Route::getRoutes() as $route) {
            $uris[] = '/' . ltrim($route->uri(), '/');
        }

        $this->assertContains('/{path}', $uris, '短地址路由不见了，图片直链会 404');
        $this->assertContains('/uploads/{path}', $uris, '老地址路由被删了，已经发出去的链接会失效');
    }
}
