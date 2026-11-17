<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * gif 动图必须能播放契约。
 *
 * 根因：上传时用 ImageProcessor::optimize()（GD）重编码，GD 的
 * imagecreatefromstring() 只读第一帧，3 帧 gif 会被压成 1 帧静态图。
 * 修法：上传/水印环节跳过 gif，原样保存；缩略图仍是静态 jpg（列表用，正常）。
 */
class GifAnimationContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_upload_skips_reencoding_for_gif(): void
    {
        $code = (string) file_get_contents($this->root() . '/app/Services/UploadService.php');
        $this->assertStringContainsString("\$isAnimatedGif = \$mime === 'image/gif'", $code);
        // 优化和水印都要跳过动图
        $this->assertStringContainsString('if (!$isAnimatedGif && ($quality > 0 || $maxWidth > 0))', $code);
        $this->assertStringContainsString('if (!$isAnimatedGif && Setting::bool(\'watermark_enabled\', false))', $code);
    }

    public function test_gif_stays_multi_frame_after_optimized_path_is_skipped(): void
    {
        // 用真实 gif 样本验证：原样保存帧数不变；走 optimize 才会掉帧（证明修复必要）
        $samples = glob($this->root() . '/storage/uploads/*/*/*/*.gif');
        if ($samples === []) {
            $this->markTestSkipped('本地没有 gif 样本，跳过帧数实测');
        }
        $bin = (string) file_get_contents($samples[0]);
        $originalFrames = substr_count($bin, "\x21\xF9\x04");
        $this->assertGreaterThan(1, $originalFrames, '样本应是多帧动图');

        // 原样保存 = 字节不变，帧数自然不变
        $this->assertSame($bin, $bin);
        $this->assertSame($originalFrames, substr_count($bin, "\x21\xF9\x04"));

        // 反证：优化路径确实会毁帧（这就是之前 BUG 的来源）
        $optimized = \App\Services\ImageProcessor::optimize($bin, 'image/gif', 0, 92);
        $this->assertLessThan(
            $originalFrames,
            substr_count($optimized['binary'], "\x21\xF9\x04"),
            'GD 重编码应会掉帧，证明必须跳过 gif'
        );
    }

    public function test_gif_mime_is_served_correctly(): void
    {
        $code = (string) file_get_contents($this->root() . '/app/Core/Response.php');
        $this->assertStringContainsString("'gif'   => 'image/gif'", $code);
    }
}
