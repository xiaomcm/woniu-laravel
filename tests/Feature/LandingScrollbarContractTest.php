<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 页面滚动条契约。
 *
 * 用户要求去掉最右侧那条滚动条（原先是嫌默认太粗，后来直接不要了）。
 * 隐藏的是显示，滚动功能必须保留：滚轮、触摸滑动、键盘翻页照常。
 */
class LandingScrollbarContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_page_scrollbar_is_hidden_globally(): void
    {
        $css = (string) file_get_contents($this->root() . '/public/assets/app.css');
        // Firefox 标准属性
        $this->assertStringContainsString('scrollbar-width: none', $css);
        // Chromium 私有伪元素
        $this->assertStringContainsString('html::-webkit-scrollbar { width: 0; height: 0; }', $css);
        $this->assertStringContainsString('-ms-overflow-style: none', $css);
    }

    public function test_landing_does_not_redraw_scrollbar(): void
    {
        // 落地页原先自己定义过蓝色细滚动条，会盖掉全局隐藏，这里不能有
        $css = (string) file_get_contents($this->root() . '/public/assets/landing.css');
        $this->assertStringNotContainsString('::-webkit-scrollbar-thumb', $css);
        $this->assertStringNotContainsString('scrollbar-color:', $css);
        // 横向滚动仍然要藏，避免光球溢出造成左右晃
        $this->assertStringContainsString('.lx-wrap { overflow-x: hidden;', $css);
    }

    public function test_scrolling_still_works(): void
    {
        // 隐藏滚动条不能顺手把 overflow 关掉，那会让页面滚不动
        $css = (string) file_get_contents($this->root() . '/public/assets/app.css');
        $this->assertStringNotContainsString('html { overflow: hidden', $css);
        $this->assertStringNotContainsString('body { overflow: hidden', $css);
    }
}
