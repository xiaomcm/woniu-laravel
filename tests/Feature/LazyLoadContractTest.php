<?php

namespace Tests\Feature;

use Illuminate\Support\HtmlString;
use Tests\TestCase;

/**
 * 图片懒加载（后台开关）契约测试。
 *
 * 需求：后台「系统设置 → 图片处理」里能开关图片延迟加载（懒加载）。
 *
 * 现状是所有列表图的 <img> 都**硬编码**了 loading="lazy"，
 * 也就是说后台根本关不掉、也统一不了。这里锁四件事：
 *   1. 设置项 lazy_load_enabled 存在，默认开启，且在 BOOL_KEYS 白名单里（能存）
 *   2. 设置页有该开关
 *   3. 视图里不再有硬编码的 loading="lazy"，统一走 lazy_attr()
 *   4. lazy_attr() 返回 HtmlString —— Blade 的 {{ }} 会把引号转义成 &quot;，
 *      属性就废了（这是本功能踩过的第一个坑）
 *
 * 另外顺带锁住设置页的卡片间隙：卡片靠 `.card + .card` 的相邻兄弟选择器留白，
 * 中间插一个 <script> 相邻关系就断了，间距整个消失。
 */
class LazyLoadContractTest extends TestCase
{
    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * 列表类图片所在的视图：这些位置应该统一用 lazy_attr()。
     *
     * @return array<string, string>
     */
    private function listImageViews(): array
    {
        $v = $this->projectRoot() . '/resources/views';

        return [
            '首页瀑布流'       => $v . '/home.blade.php',
            '我的图库'         => $v . '/images.blade.php',
            '我的相册'         => $v . '/albums.blade.php',
            '相册详情'         => $v . '/album_show.blade.php',
            '发现广场'         => $v . '/explore.blade.php',
            '图片详情相关推荐' => $v . '/image_detail.blade.php',
            '分享页'           => $v . '/share.blade.php',
            '用户主页'         => $v . '/user_home.blade.php',
            '落地页'           => $v . '/landing.blade.php',
            '后台控制台'       => $v . '/admin/dashboard.blade.php',
            '后台图片管理'     => $v . '/admin/images.blade.php',
        ];
    }

    // ---------------------------------------------------------------
    // 设置项
    // ---------------------------------------------------------------

    public function test_setting_default_is_enabled(): void
    {
        $defaults = \App\Core\Setting::defaults();

        $this->assertArrayHasKey(
            'lazy_load_enabled',
            $defaults,
            '设置项 lazy_load_enabled 缺失'
        );
        $this->assertSame(
            '1',
            (string) $defaults['lazy_load_enabled'],
            '懒加载默认应当开启（此前视图里是写死开启的，默认值要跟实际行为一致）'
        );
    }

    public function test_setting_is_in_bool_keys_whitelist(): void
    {
        // 开关型设置项必须在 BOOL_KEYS 里，否则保存时会被丢掉
        $controller = (string) file_get_contents(
            $this->projectRoot() . '/app/Http/Controllers/Admin/SettingController.php'
        );

        $this->assertMatchesRegularExpression(
            '/BOOL_KEYS\s*=\s*\[.*?lazy_load_enabled/s',
            $controller,
            'lazy_load_enabled 不在 BOOL_KEYS 白名单里，后台存不进去'
        );
    }

    public function test_settings_page_has_the_switch(): void
    {
        $blade = (string) file_get_contents(
            $this->projectRoot() . '/resources/views/admin/settings.blade.php'
        );

        $this->assertStringContainsString(
            'name="lazy_load_enabled"',
            $blade,
            '设置页没有懒加载开关'
        );
        $this->assertStringContainsString(
            '图片延迟加载',
            $blade,
            '设置页缺少懒加载开关的中文说明'
        );
    }

    // ---------------------------------------------------------------
    // 视图改造
    // ---------------------------------------------------------------

    public function test_no_view_hardcodes_loading_lazy(): void
    {
        foreach ($this->listImageViews() as $label => $file) {
            $blade = (string) file_get_contents($file);

            $this->assertStringNotContainsString(
                'loading="lazy"',
                $blade,
                "{$label} 还在硬编码 loading=\"lazy\"，这样后台开关关不掉"
            );
        }
    }

    public function test_list_image_views_use_lazy_attr(): void
    {
        foreach ($this->listImageViews() as $label => $file) {
            $blade = (string) file_get_contents($file);

            $this->assertStringContainsString(
                'lazy_attr()',
                $blade,
                "{$label} 的列表图没有改用 lazy_attr()"
            );
        }
    }

    public function test_current_viewed_image_is_not_lazy(): void
    {
        // 详情页正在看的那张大图必须立即加载，不能跟着开关走
        $blade = (string) file_get_contents(
            $this->projectRoot() . '/resources/views/image_detail.blade.php'
        );

        $main = '';
        if (preg_match('/<img src="\{\{ \(string\) \$image\[.url.\] \}\}".*?>/s', $blade, $m)) {
            $main = $m[0];
        }

        $this->assertNotSame('', $main, '没找到详情页主图的 img 标签');
        $this->assertStringNotContainsString(
            'lazy_attr()',
            $main,
            '详情页主图被加上了懒加载，用户点了半天才看到图'
        );
    }

    public function test_brand_logo_and_captcha_are_not_lazy(): void
    {
        // Logo、验证码、支付二维码必须立即显示
        foreach (['layouts/site.blade.php', 'layouts/landing.blade.php'] as $layout) {
            $blade = (string) file_get_contents($this->projectRoot() . '/resources/views/' . $layout);
            $this->assertStringNotContainsString('lazy_attr()', $blade, "{$layout} 的 Logo 不该懒加载");
        }

        $register = (string) file_get_contents(
            $this->projectRoot() . '/resources/views/auth/register.blade.php'
        );
        $this->assertStringNotContainsString('lazy_attr()', $register, '验证码图片不该懒加载');

        $payQr = (string) file_get_contents(
            $this->projectRoot() . '/resources/views/pay_qr.blade.php'
        );
        $this->assertStringNotContainsString('lazy_attr()', $payQr, '支付二维码不该懒加载');
    }

    // ---------------------------------------------------------------
    // helper 本身
    // ---------------------------------------------------------------

    public function test_lazy_attr_returns_html_string(): void
    {
        // Blade 的 {{ }} 会转义引号：返回裸字符串的话属性会变成 &quot;lazy&quot;
        $result = lazy_attr();

        $this->assertInstanceOf(
            HtmlString::class,
            $result,
            'lazy_attr() 必须返回 HtmlString，否则 Blade {{ }} 会把引号转义掉'
        );
    }

    public function test_lazy_attr_is_empty_when_switch_is_off(): void
    {
        \App\Core\Setting::reset();
        \App\Core\Setting::set('lazy_load_enabled', '0');

        $this->assertSame('', (string) lazy_attr(), '开关关闭时应输出空串');
    }

    public function test_lazy_attr_outputs_lazy_and_async_when_switch_is_on(): void
    {
        \App\Core\Setting::reset();
        \App\Core\Setting::set('lazy_load_enabled', '1');

        $out = (string) lazy_attr();

        $this->assertStringContainsString('loading="lazy"', $out);
        $this->assertStringContainsString('decoding="async"', $out);
    }

    // ---------------------------------------------------------------
    // 设置页卡片间隙
    // ---------------------------------------------------------------

    public function test_settings_page_has_no_element_between_cards(): void
    {
        // .card + .card 是相邻兄弟选择器：中间夹一个 <script> 间距就没了
        $blade = (string) file_get_contents(
            $this->projectRoot() . '/resources/views/admin/settings.blade.php'
        );

        // 去掉 script / style / 注释之后，所有 card 仍应保持「直接相邻」：
        // 也就是说原文里不该出现「card 结束 → script → card 开始」这种夹法
        $this->assertDoesNotMatchRegularExpression(
            '/<\/div>\s*<(script|style)\b.*?<\/\1>\s*<div class="card">/s',
            $blade,
            '设置页有 script 夹在两个 .card 之间，相邻兄弟选择器失效，卡片之间会没有间隙'
        );
    }

    public function test_card_adjacent_sibling_rule_exists(): void
    {
        $css = (string) file_get_contents($this->projectRoot() . '/public/assets/app.css');

        $this->assertMatchesRegularExpression(
            '/\.card\s*\+\s*\.card\s*\{[^}]*margin-top\s*:\s*\d+px/',
            $css,
            'app.css 里没有 .card + .card 的间距规则'
        );
    }
}
