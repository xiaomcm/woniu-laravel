<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 页面块级间距契约测试。
 *
 * 背景：卡片间距原先只靠 `.card + .card { margin-top: 18px }` 这条**相邻兄弟**规则。
 * 相邻兄弟要求两个元素在 DOM 里紧挨着，中间插入任何节点（.two-col / .stat-grid
 * 这类容器，或者 <script> 这种不渲染的标签）相邻关系就断了，后一块拿不到任何
 * 间距 → 两块内容直接 0px 贴死，视觉上像粘在一起。
 *
 * 实测踩过的坑：
 *   - 用户中心 /settings：.two-col（账户资料 / 修改密码）与下一张 .card（容量与用量）之间 0px
 *   - 后台首页 /admin/：stat-grid → 卡片、卡片 → .two-col、.two-col → 卡片 三处 0px
 *
 * 现在的做法：间距改由「前一块自己的 margin-bottom」提供，链条不再被容器打断。
 * 这里锁死三件事：
 *   1. .content 下的三类栈级块（.card / .two-col / .stat-grid）都必须带 margin-bottom
 *   2. .card + .card 这条兜底规则仍在（非 .content 容器里的卡片靠它）
 *   3. 视图里不再用内联 margin-top 手工补间距（那正是绕过统一规则的野路子）
 */
class BlockGapContractTest extends TestCase
{
    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private function css(): string
    {
        return (string) file_get_contents($this->projectRoot() . '/public/assets/app.css');
    }

    public function test_stack_blocks_in_content_carry_bottom_margin(): void
    {
        $css = $this->css();

        // 三类栈级块必须共享同一条 margin-bottom 规则
        foreach (['card', 'two-col', 'stat-grid'] as $block) {
            $this->assertMatchesRegularExpression(
                '/\.content\s*>\s*\.' . $block . '\b[^{]*\{[^}]*margin-bottom\s*:\s*\d+px/',
                $css,
                ".content > .{$block} 缺少 margin-bottom，容器会打断卡片之间的间距"
            );
        }
    }

    public function test_card_adjacent_sibling_fallback_still_exists(): void
    {
        // 非 .content 容器（例如 .card-body 内、site-main 里的孤立卡片）仍靠这条兜底
        $this->assertMatchesRegularExpression(
            '/\.card\s*\+\s*\.card\s*\{[^}]*margin-top\s*:\s*\d+px/',
            $this->css(),
            'app.css 里没有 .card + .card 的兜底间距规则'
        );
    }

    public function test_views_do_not_inline_manual_stack_gap(): void
    {
        // 栈级卡片上手写内联 margin-top 是绕过统一规则的老写法：
        // 容器一插进来照样失效。这里只查「栈级卡片」上的大号内联 margin-top，
        // 卡片内部 4px / 8px 那种微调是正常的，不在管辖范围。
        $views = [
            '/resources/views/home.blade.php',
            '/resources/views/settings.blade.php',
            '/resources/views/admin/dashboard.blade.php',
        ];

        foreach ($views as $view) {
            $path = $this->projectRoot() . $view;
            $this->assertFileExists($path, "{$view} 不存在");

            $blade = (string) file_get_contents($path);

            preg_match_all('/<div\b[^>]*class="[^"]*\bcard\b[^"]*"[^>]*>/', $blade, $tags);

            foreach ($tags[0] as $tag) {
                if (!preg_match('/style="[^"]*margin-top\s*:\s*(\d+)px/', $tag, $m)) {
                    continue;
                }

                $this->assertLessThan(
                    12,
                    (int) $m[1],
                    "{$view} 的栈级卡片上出现内联 margin-top:{$m[1]}px，应交给 .content 栈级块规则统一处理"
                );
            }
        }
    }

    public function test_user_center_and_admin_dashboard_use_stack_containers(): void
    {
        // 这两个页面是本次缺陷的现场：栈级块之间必须靠容器（.two-col / .stat-grid）
        // 分隔而不是靠相邻兄弟，否则容器一插进来间距就断
        $settings = (string) file_get_contents(
            $this->projectRoot() . '/resources/views/settings.blade.php'
        );
        $dashboard = (string) file_get_contents(
            $this->projectRoot() . '/resources/views/admin/dashboard.blade.php'
        );

        $this->assertStringContainsString(
            'class="two-col"',
            $settings,
            '用户中心应当用 .two-col 承载并排的两张卡片'
        );
        $this->assertStringContainsString(
            'class="stat-grid"',
            $dashboard,
            '后台首页应当用 .stat-grid 承载统计卡片'
        );
    }
}
