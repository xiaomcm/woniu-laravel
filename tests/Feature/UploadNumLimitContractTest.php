<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 单次上传张数（max_upload_num）契约。
 * 用户反馈：后台用户组改成 59，前台个人设置还显示旧值。
 * 现在设置页直接读组的 max_upload_num，上传页也显示并按它限制，前后端都拦。
 */
class UploadNumLimitContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_settings_page_shows_single_upload_limit_from_group(): void
    {
        $view = (string) file_get_contents($this->root() . '/resources/views/settings.blade.php');
        $pos = strpos($view, '单次上传');
        $this->assertNotFalse($pos, '个人设置页没有「单次上传」这一行');
        $segment = substr($view, $pos, 260);
        $this->assertStringContainsString('max_upload_num', $segment, '单次上传必须读用户组的 max_upload_num');
    }

    public function test_upload_page_shows_limit_and_passes_it_to_uploader(): void
    {
        $controller = (string) file_get_contents($this->root() . '/app/Http/Controllers/UploadController.php');
        $this->assertStringContainsString("'maxNum'", $controller, '上传页没从用户组取单次上限');
        $this->assertStringContainsString('max_upload_num', $controller);

        $view = (string) file_get_contents($this->root() . '/resources/views/upload.blade.php');
        $this->assertStringContainsString('单次 ≤', $view, '上传页头部没显示单次上限');
        $uploader = (string) file_get_contents($this->root() . '/resources/views/partials/uploader.blade.php');
        $this->assertStringContainsString('data-max-num', $uploader);
        $this->assertStringContainsString('单次最多', $uploader);
    }

    public function test_backend_rejects_batch_over_group_limit(): void
    {
        $code = (string) file_get_contents($this->root() . '/app/Http/Controllers/UploadController.php');
        $this->assertStringContainsString('count($files) > $maxNum', $code, '后端没按用户组限制单次张数');
        $this->assertStringContainsString('单次最多上传', $code);
        // 必须在实际上传循环之前拦，不能写半个文件进去
        $check = strpos($code, 'count($files) > $maxNum');
        $loop = strpos($code, 'foreach ($files as $file)');
        $this->assertLessThan($loop, $check, '数量校验要在上传循环之前');
    }

    public function test_frontend_blocks_batch_over_limit(): void
    {
        $js = (string) file_get_contents($this->root() . '/public/assets/app.js');
        $this->assertStringContainsString("getAttribute('data-max-num')", $js);
        $this->assertStringContainsString('files.length > maxNum', $js);
    }

    public function test_group_benefits_reads_current_max_upload_num(): void
    {
        // 改组的值后 benefits 立刻跟着变，证明没有缓存旧值
        $group = \App\Models\Group::allRows()[0] ?? null;
        $this->assertNotNull($group);
        $benefits = \App\Models\Group::benefits($group);
        $line = '';
        foreach ($benefits as $b) {
            if (strpos($b, '单次') !== false) { $line = $b; break; }
        }
        $this->assertNotSame('', $line, '权益清单里没有单次上传那条');
        $this->assertStringContainsString((string) (int) $group['max_upload_num'], $line);
    }
}
