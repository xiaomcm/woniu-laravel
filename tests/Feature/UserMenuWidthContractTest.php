<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 顶栏用户下拉菜单「面板宽度」契约测试。
 *
 * 用户反馈：下拉面板右侧预留了一大片空白（截图里红框标出），不协调。
 * 根因是 .user-dropdown 写死了 min-width: 208px，而菜单项文字只有 ~96px，
 * 右边空出 110px。
 *
 * 修法：面板改成 width: max-content 贴合内容宽度，菜单项文字不再右侧留空。
 * 这里锁四件事：
 *   1. .user-dropdown 必须用 width:max-content，不能再有写死的 min-width: 208px
 *   2. 窄屏媒体查询里那条覆盖规则同样不能写死 width: 200px
 *   3. 菜单项要 white-space:nowrap（否则窄屏会折行撑高面板）
 *   4. 头部要 overflow:hidden + max-width 上限（超长用户名/邮箱不能把面板撑爆）
 */
class UserMenuWidthContractTest extends TestCase
{
    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return array<string, string>
     */
    private function cssFiles(): array
    {
        return [
            '本仓库' => $this->projectRoot() . '/public/assets/app.css',
        ];
    }

    public function test_dropdown_width_follows_content(): void
    {
        foreach ($this->cssFiles() as $label => $file) {
            $this->assertFileExists($file, "{$label} 的 app.css 不存在");
            $css = (string) file_get_contents($file);

            $this->assertMatchesRegularExpression(
                '/\.user-dropdown\s*\{[^}]*width\s*:\s*max-content/',
                $css,
                "{$label} 的 .user-dropdown 没有用 width:max-content，面板宽度不贴合内容"
            );

            $this->assertStringNotContainsString(
                'min-width: 208px',
                $css,
                "{$label} 的下拉面板仍写死 min-width: 208px，右侧会留出大片空白"
            );
        }
    }

    public function test_narrow_screen_override_also_follows_content(): void
    {
        foreach ($this->cssFiles() as $label => $file) {
            $css = (string) file_get_contents($file);

            // 窄屏覆盖规则里不允许再出现写死的 200px 面板宽度
            $this->assertStringNotContainsString(
                '.user-dropdown { right: 0; min-width: 0; width: 200px; }',
                $css,
                "{$label} 的窄屏规则仍写死 width:200px，窄屏下同样会留大片空白"
            );

            $this->assertMatchesRegularExpression(
                '/@media[^{]*\{[^@]*\.user-dropdown\s*\{[^}]*width\s*:\s*max-content/',
                $css,
                "{$label} 的窄屏 .user-dropdown 没有跟着改成 max-content"
            );
        }
    }

    public function test_menu_items_do_not_wrap(): void
    {
        foreach ($this->cssFiles() as $label => $file) {
            $css = (string) file_get_contents($file);

            $this->assertMatchesRegularExpression(
                '/\.user-dropdown\s+a,\s*\.user-dropdown\s+button\s*\{[^}]*white-space\s*:\s*nowrap/',
                $css,
                "{$label} 的菜单项没加 white-space:nowrap，窄屏会折行把面板撑高"
            );
        }
    }

    public function test_head_is_clamped_so_long_accounts_cannot_break_layout(): void
    {
        foreach ($this->cssFiles() as $label => $file) {
            $css = (string) file_get_contents($file);

            $this->assertMatchesRegularExpression(
                '/\.user-dropdown-head\s*\{[^}]*overflow\s*:\s*hidden/',
                $css,
                "{$label} 的 .user-dropdown-head 缺 overflow:hidden，超长用户名会撑爆面板"
            );

            $this->assertMatchesRegularExpression(
                '/\.user-dropdown-head\s*\{[^}]*max-width\s*:\s*\d+px/',
                $css,
                "{$label} 的 .user-dropdown-head 缺 max-width 上限，超长账号会把面板撑得很宽"
            );

            // strong / span 都要能省略号截断
            foreach (['strong', 'span'] as $tag) {
                $this->assertMatchesRegularExpression(
                    '/\.user-dropdown-head\s+' . $tag . '\s*\{[^}]*text-overflow\s*:\s*ellipsis/',
                    $css,
                    "{$label} 的 .user-dropdown-head {$tag} 没加 text-overflow:ellipsis"
                );
            }
        }
    }
}
