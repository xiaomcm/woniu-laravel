<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 图片卡片操作按钮契约测试。
 *
 * 背景：/images 页面上「详情页」「打开原图」两个按钮被压成 15px 宽的窄条，
 * 跟其余 27px 的按钮完全不一样（用户截图圈出反馈「这两个按钮畸形了」）。
 *
 * 根因是三件事叠加：
 *   1. .image-ops 是 flex 容器，卡片只有 ~172px 宽，6 个按钮装不下 → 触发 flex 收缩
 *   2. .op-btn 设了 width:28px 但没设 flex:none，收缩时 width 被无视
 *   3. <button> 有浏览器默认 padding:1px 6px，被 min-width:auto 保护在 27px；
 *      <a> 没有默认 padding，直接塌到 SVG 的 15px —— 所以只有这两个 <a> 变形
 *
 * 这里锁死三件事：
 *   1. .op-btn 必须设 flex:none（不被 flex 压缩）
 *   2. .op-btn 必须清掉 padding（<a> 与 <button> 渲染一致）
 *   3. .image-ops 必须允许换行（空间不足时换行，而不是压扁按钮）
 */
class OpButtonContractTest extends TestCase
{
    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private function css(): string
    {
        return (string) file_get_contents($this->projectRoot() . '/public/assets/app.css');
    }

    public function test_op_btn_is_not_shrunk_by_flex(): void
    {
        // 少了 flex:none，容器装不下时按钮宽度会被 flex 收缩掉
        $this->assertMatchesRegularExpression(
            '/\.op-btn\s*\{[^}]*flex\s*:\s*none/',
            $this->css(),
            'app.css 里 .op-btn 没有 flex:none，按钮会被 flex 容器压扁'
        );
    }

    public function test_op_btn_resets_default_padding(): void
    {
        // <button> 默认 padding:1px 6px，<a> 没有 —— 不清零的话两种标签差一圈
        $this->assertMatchesRegularExpression(
            '/\.op-btn\s*\{[^}]*padding\s*:\s*0/',
            $this->css(),
            'app.css 里 .op-btn 没有清零 padding，<a> 和 <button> 会渲染成不同宽度'
        );
    }

    public function test_op_btn_has_fixed_square_size(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.op-btn\s*\{[^}]*width\s*:\s*28px[^}]*height\s*:\s*28px/s',
            $this->css(),
            'app.css 里 .op-btn 不是固定的 28×28 正方形'
        );
    }

    public function test_image_ops_allows_wrapping(): void
    {
        // 空间不够时换行，而不是把按钮压成窄条
        $this->assertMatchesRegularExpression(
            '/\.image-ops\s*\{[^}]*flex-wrap\s*:\s*wrap/',
            $this->css(),
            'app.css 里 .image-ops 没有 flex-wrap:wrap，按钮装不下会被压扁'
        );
    }

    public function test_anchor_buttons_use_op_btn_class(): void
    {
        // 页面里用 <a class="op-btn"> 的地方（详情页 / 打开原图）必须带 op-btn 类，
        // 否则拿不到统一样式
        $views = [
            '/resources/views/images.blade.php',
            '/resources/views/admin/images.blade.php',
            '/resources/views/home.blade.php',
            '/resources/views/album_show.blade.php',
        ];

        foreach ($views as $view) {
            $path = $this->projectRoot() . $view;
            $this->assertFileExists($path, "{$view} 不存在");

            $blade = (string) file_get_contents($path);

            // 抓 <a ...> 标签里带 op-btn 的（详情页 / 打开原图），确认都带 class
            preg_match_all('/<a\b[^>]*op-btn[^>]*>/', $blade, $tags);

            foreach ($tags[0] as $tag) {
                $this->assertMatchesRegularExpression(
                    '/class="[^"]*\bop-btn\b/',
                    $tag,
                    "{$view} 里有 <a> 用了 op-btn 却没写 class=\"op-btn\"，样式会失效"
                );
            }
        }
    }
}
