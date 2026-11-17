<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 手机端 UI 契约：筛选栏不堆成竖条、抽屉侧栏能关掉。
 */
class MobileToolbarAndNavContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_both_layouts_have_backdrop_and_close_button(): void
    {
        foreach (['app', 'admin'] as $name) {
            $code = (string) file_get_contents($this->root() . "/resources/views/layouts/{$name}.blade.php");
            $this->assertStringContainsString(
                'data-nav-backdrop',
                $code,
                "{$name} 布局缺抽屉遮罩，侧栏打开后点外面关不掉"
            );
            $this->assertStringContainsString(
                'data-sidebar-close',
                $code,
                "{$name} 布局缺侧栏关闭按钮"
            );
        }
    }

    public function test_js_can_close_drawer_three_ways(): void
    {
        $js = (string) file_get_contents($this->root() . '/public/assets/app.js');

        // 点遮罩、点关闭按钮、按 Esc 三种方式都要能关
        $this->assertStringContainsString('data-nav-backdrop', $js);
        $this->assertStringContainsString('data-sidebar-close', $js);
        $this->assertStringContainsString("e.key === 'Escape'", $js);
        $this->assertStringContainsString('function closeSidebar()', $js);
        $this->assertStringContainsString('syncNavBackdrop', $js, '开关抽屉要同步遮罩状态');
    }

    public function test_toolbar_uses_grid_on_narrow_screens(): void
    {
        $css = (string) file_get_contents($this->root() . '/public/assets/app.css');

        // 窄屏筛选栏用两列网格：搜索框独占一行，三个下拉两两并排，筛选按钮补最后一格
        $this->assertStringContainsString(
            'display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));',
            $css,
            '窄屏筛选栏没有改成两列 grid 布局'
        );
        $this->assertStringContainsString(
            '.toolbar form .input { grid-column: 1 / -1;',
            $css,
            '搜索框没有占满一行'
        );
        $this->assertStringContainsString(
            '.toolbar-switch { display: flex; gap: 8px; width: 100%; }',
            $css,
            '正常/回收站没有包成并排容器'
        );
        // 前台和后台图片页都要用 toolbar-switch 包住切换按钮
        foreach (['images.blade.php', 'admin/images.blade.php'] as $view) {
            $code = (string) file_get_contents($this->root() . '/resources/views/' . $view);
            $this->assertStringContainsString('class="toolbar-switch"', $code, "{$view} 没用 toolbar-switch");
        }
    }

    public function test_backdrop_z_index_between_content_and_sidebar(): void
    {
        $css = (string) file_get_contents($this->root() . '/public/assets/app.css');
        // 遮罩要盖住内容/顶栏但盖不住侧栏本身
        $this->assertStringContainsString(
            'position: fixed; inset: 0; z-index: 50;',
            $css,
            '遮罩层级不对'
        );
        $this->assertStringContainsString('z-index: 60;', $css, '侧栏要在遮罩之上');
    }
}
