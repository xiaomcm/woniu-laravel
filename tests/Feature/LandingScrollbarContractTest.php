<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 落地页滚动条契约。
 * 落地页内容长（手机视口约 5600px），垂直滚动不可避免；
 * 用户嫌浏览器默认滚动条太粗，这里把样式收细、融进深色背景。
 */
class LandingScrollbarContractTest extends TestCase
{
    public function test_landing_uses_thin_scrollbar(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/landing.css');
        // Firefox 标准属性 + Chromium 私有伪元素，两者都要有
        $this->assertStringContainsString('scrollbar-width: thin', $css);
        $this->assertStringContainsString('scrollbar-color:', $css);
        $this->assertStringContainsString('::-webkit-scrollbar { width: 5px', $css);
        $this->assertStringContainsString('::-webkit-scrollbar-thumb', $css);
        $this->assertStringContainsString('::-webkit-scrollbar-track', $css);
        // 横向仍然是隐藏的，避免光球溢出造成左右滑
        $this->assertStringContainsString('.lx-wrap { overflow-x: hidden;', $css);
    }
}
