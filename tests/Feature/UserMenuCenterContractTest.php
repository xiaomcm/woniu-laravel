<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 顶栏用户下拉菜单（.user-dropdown）头部居中契约测试。
 *
 * 下拉面板头部有两行：用户名（strong）+ 角色与邮箱（span）。
 * 用户要求这两行水平居中显示 —— 头部容器必须带 text-align:center，
 * 否则文字会贴左，跟面板下方左对齐的菜单项形成割裂感。
 *
 * 这里锁三件事：
 *   1. .user-dropdown-head 带 text-align:center
 *   2. 用户名与副标题是块级元素，居中才生效
 *   3. 菜单项保持左对齐
 */
class UserMenuCenterContractTest extends TestCase
{
    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * 本仓库 app.css 路径。
     *
     * @return array<string, string>
     */
    private function cssFiles(): array
    {
        return [
            '本仓库' => $this->projectRoot() . '/public/assets/app.css',
        ];
    }

    public function test_dropdown_head_is_text_centered(): void
    {
        foreach ($this->cssFiles() as $label => $file) {
            $this->assertFileExists($file, "{$label} 的 app.css 不存在");

            $css = (string) file_get_contents($file);

            $this->assertMatchesRegularExpression(
                '/\.user-dropdown-head\s*\{[^}]*text-align\s*:\s*center/',
                $css,
                "{$label} 的 .user-dropdown-head 没有 text-align:center，用户名与副标题没有居中"
            );
        }
    }

    public function test_user_name_and_subtitle_are_block_level_so_center_applies(): void
    {
        foreach ($this->cssFiles() as $label => $file) {
            $css = (string) file_get_contents($file);

            // strong / span 必须是 block，inline 元素上的 text-align 才不会按预期铺满整行
            $this->assertMatchesRegularExpression(
                '/\.user-dropdown-head\s+strong\s*\{[^}]*display\s*:\s*block/',
                $css,
                "{$label} 的 .user-dropdown-head strong 不是 block，居中会失效"
            );
            $this->assertMatchesRegularExpression(
                '/\.user-dropdown-head\s+span\s*\{[^}]*display\s*:\s*block/',
                $css,
                "{$label} 的 .user-dropdown-head span 不是 block，居中会失效"
            );
        }
    }

    public function test_menu_items_stay_left_aligned(): void
    {
        // 头部居中，但下方的菜单项必须保持左对齐（图标+文字左对齐才好看）
        foreach ($this->cssFiles() as $label => $file) {
            $css = (string) file_get_contents($file);

            $this->assertMatchesRegularExpression(
                '/\.user-dropdown\s+a,\s*\.user-dropdown\s+button\s*\{[^}]*text-align\s*:\s*left/',
                $css,
                "{$label} 的菜单项被改成非左对齐，会和居中的头部割裂"
            );
        }
    }
}
