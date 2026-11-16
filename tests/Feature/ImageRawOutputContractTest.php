<?php

namespace Tests\Feature;

use App\Core\Setting;
use Illuminate\Support\Facades\DB;
use Tests\DatabaseTestCase;

/**
 * 图片直链必须原样吐字节。
 *
 * 之前有个坑：outputLocal() 里 `'fit' => Request::input('fit', 'cover')` 默认值非空，
 * array_filter 之后 $ops 永远不空，「无参数直接输出原文件」那条路永远走不到，
 * 结果所有本地图片直链都被 ImageProcessor 重新编码一遍（17462 字节的图吐出去 16958 字节），
 * 图床最该保证的「直链就是原图」直接失守。
 *
 * 这里用真实 HTTP 请求锁住行为：请求一个已知字节的文件，响应内容 sha1 必须和磁盘一致。
 */
class ImageRawOutputContractTest extends DatabaseTestCase
{
    /** 测试过程中落到真实 uploads 目录的文件，跑完要清掉，别留脏数据 */
    private array $fixtures = [];

    protected function tearDown(): void
    {
        $root = dirname(__DIR__, 2) . '/storage/uploads';
        foreach ($this->fixtures as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
            // 顺带把空掉的日期目录收掉（只收到 uploads 这一层为止）
            $dir = dirname($file);
            while ($dir !== $root && str_starts_with($dir, $root) && is_dir($dir)) {
                $items = @scandir($dir);
                if ($items === false || count($items) > 2) {
                    break;
                }
                @rmdir($dir);
                $dir = dirname($dir);
            }
        }
        $this->fixtures = [];

        parent::tearDown();
    }

    private function writeFixture(string $pathname, string $bytes): string
    {
        $root = dirname(__DIR__, 2) . '/storage/uploads';
        $file = $root . '/' . $pathname;
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        file_put_contents($file, $bytes);
        $this->fixtures[] = $file;

        return $file;
    }

    private function registerImage(string $pathname, string $sha1, int $size): int
    {
        return (int) DB::table('images')->insertGetId([
            'user_id'       => 1,
            'album_id'      => null,
            'storage_id'    => 1,
            'name'          => 'raw.png',
            'original_name' => 'raw.png',
            'pathname'      => $pathname,
            'mimetype'      => 'image/png',
            'extension'     => 'png',
            'size'          => $size,
            'md5'           => md5($sha1),
            'sha1'          => $sha1,
            'width'         => 10,
            'height'        => 10,
            'url'           => '/uploads/' . $pathname,
            'status'        => 1,
            'is_public'     => 1,
            'views'         => 0,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    public function test_direct_link_returns_exact_same_bytes(): void
    {
        // 造一张有实际内容的 PNG，字节数量故意不是整百，方便一眼看出有没有被重编码
        $binary = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAFUlEQVR42mP8z8BQz0AEYBxVSF+FAP5FDvcfRYWgAAAAAElFTkSuQmCC'
        );
        $pathname = '2099/01/01/raw-output-test.png';
        $file = $this->writeFixture($pathname, $binary);
        $sha1 = sha1_file($file);

        $this->registerImage($pathname, $sha1, (int) filesize($file));

        $response = $this->get('/uploads/' . $pathname);
        $response->assertOk();

        $body = $response->streamedContent() ?: $response->getContent();

        $this->assertSame(
            $sha1,
            sha1($body),
            '直链返回的内容和磁盘文件不一致 —— 说明图片又被重新编码了'
        );
        $this->assertSame(
            strlen($binary),
            strlen($body),
            '直链返回的字节数和原文件不一样'
        );
    }

    public function test_direct_link_with_width_param_still_returns_a_valid_image(): void
    {
        // 带 w 参数时走处理流程。具体缩放到多少由 ImageProcessor 决定，
        // 这里只确认「没被开关逻辑挡住、返回的仍是一张能用的图」。
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAGQAAABkCAYAAABw4pVUAAAAJ0lEQVR42u3BAQ0AAADCoPdPbQ43oAAAAAAAAAAAAAAAAAAAAAB4NwYQAAABlKOMAAAAAElFTkSuQmCC'
        );
        $pathname = '2099/01/01/raw-process-test.png';
        $file = $this->writeFixture($pathname, $png);
        $this->registerImage($pathname, sha1_file($file), (int) filesize($file));

        $response = $this->get('/uploads/' . $pathname . '?w=10');
        $response->assertOk();

        $body = $response->streamedContent() ?: $response->getContent();
        $info = @getimagesizefromstring((string) $body);

        $this->assertNotFalse($info, '带 w 参数时应该返回一张有效图片');
        $this->assertGreaterThan(0, (int) $info[0], '返回图片的宽度应该大于 0');
    }

    public function test_output_local_does_not_default_fit_unconditionally(): void
    {
        // 源码层兜底：fit 的默认值不能被无条件塞进 ops
        $code = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Http/Controllers/ImageController.php'
        );

        $this->assertStringNotContainsString(
            "array_filter(\$ops, static fn",
            $code,
            '不要再靠 array_filter 过滤默认值来判断「有没有参数」，fit 的默认值会把它废掉'
        );
        $this->assertStringContainsString(
            '只有真传了参数才处理',
            $code,
            'outputLocal 里缺少「无参数直接吐原文件」的说明与实现'
        );
    }
}
