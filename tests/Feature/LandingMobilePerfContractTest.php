<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 落地页手机端滑动性能降级。
 *
 * 手机上卡顿的三个大头：backdrop-filter 玻璃拟态、filter:blur(46px) 大光球、
 * 无限循环的网格动画，外加一直在跑的粒子画布。这里钉住降级规则，
 * 防止以后改样式时把这些高开销效果又加回去。
 */
class LandingMobilePerfContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_mobile_disables_backdrop_filter(): void
    {
        $css = (string) file_get_contents($this->root() . '/public/assets/landing.css');
        // 移动端降级段里 backdrop-filter 必须被关掉
        $this->assertStringContainsString('@media (max-width: 768px)', $css);
        $this->assertStringContainsString('backdrop-filter: none', $css);
        // 触屏设备段（不限屏幕宽度）同样要降级
        $this->assertStringContainsString('@media (hover: none) and (pointer: coarse)', $css);
    }

    public function test_mobile_removes_heavy_blur_and_grid_animation(): void
    {
        $css = (string) file_get_contents($this->root() . '/public/assets/landing.css');
        $this->assertStringContainsString('.lx-orb {', $css);
        $this->assertStringContainsString('filter: none', $css);
        $this->assertStringContainsString('.lx-grid { animation: none !important; }', $css);
    }

    public function test_mobile_hides_particle_canvas(): void
    {
        $css = (string) file_get_contents($this->root() . '/public/assets/landing.css');
        $js = (string) file_get_contents($this->root() . '/public/assets/landing.js');
        // CSS 兜底：窄屏直接藏画布，不依赖 JS 判断是否执行
        $this->assertStringContainsString('#lx-canvas { display: none !important; }', $css);
        // JS 侧也判断触屏直接不初始化
        $this->assertStringContainsString('navigator.maxTouchPoints', $js);
        $this->assertStringContainsString("cv.style.display = 'none'", $js);
    }

    public function test_canvas_pauses_when_scrolling_and_hidden_tab(): void
    {
        $js = (string) file_get_contents($this->root() . '/public/assets/landing.js');
        // 滚动时暂停粒子，别跟用户抢帧
        $this->assertStringContainsString('function start()', $js);
        $this->assertStringContainsString('function stop()', $js);
        $this->assertStringContainsString('visibilitychange', $js);
        $this->assertStringContainsString('cancelAnimationFrame', $js);
    }

    public function test_landing_scripts_are_deferred(): void
    {
        $layout = (string) file_get_contents($this->root() . '/resources/views/layouts/landing.blade.php');
        // 落地页脚本不阻塞首屏渲染
        $this->assertStringContainsString("asset('app.js') }}\" defer", $layout);
        $this->assertStringContainsString("asset('landing.js') }}\" defer", $layout);
    }
}
