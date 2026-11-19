<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 关于我们 / 服务条款 / 隐私政策 三页 + 全站页脚入口 + 注册勾选契约。
 */
class LegalPagesContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_three_pages_seeded_with_full_content(): void
    {
        $defs = \App\Core\LegalPages::definitions();
        $this->assertSame(['about', 'terms', 'privacy'], array_keys($defs));
        foreach ($defs as $slug => $page) {
            $this->assertSame($slug, $page['slug']);
            $this->assertGreaterThan(200, mb_strlen($page['content']), "{$slug} 正文应是完整版");
        }
        // 新装种子也引用同一份正文，不是旧的占位
        $seed = (string) file_get_contents($this->root() . '/app/Core/SchemaExtra.php');
        $this->assertStringContainsString('LegalPages::definitions()', $seed);
        $this->assertStringNotContainsString('蜗牛图床提供稳定、快速、可扩展的图片托管服务。', $seed);
    }

    public function test_old_placeholder_pages_upgraded_but_custom_content_kept(): void
    {
        $this->assertStringContainsString(
            'upgradeLegalPages',
            (string) file_get_contents($this->root() . '/app/Core/Migrator.php')
        );
        // 只更新仍是占位的，站主改过的不碰
        $this->assertTrue(\App\Core\LegalPages::isPlaceholder('terms',
            '<p>上传内容需遵守所在地法律法规，禁止上传违法与侵权内容。</p>'));
        $this->assertFalse(\App\Core\LegalPages::isPlaceholder('terms',
            '<p>站主自己写的条款</p>'));
    }

    public function test_footer_shows_pages_even_when_layout_does_not_pass_them(): void
    {
        // footer_meta 自己查 Page::footerPages()，不再依赖布局传参
        $fragment = (string) file_get_contents($this->root() . '/resources/views/partials/footer_meta.blade.php');
        $this->assertStringContainsString('\\App\\Models\\Page::footerPages()', $fragment);
        $this->assertStringContainsString("\$footerPages ?? null", $fragment);

        $this->app['request'] = \Illuminate\Http\Request::create('http://localhost/');
        // auth 布局是不传 footerPages 的那类
        $html = \App\Core\View::render('auth/login', [
            'pageTitle' => '登录', 'redirect' => '/', 'error' => '', 'ok' => '', 'socials' => [],
        ], 'layouts/auth');
        foreach (['关于我们', '服务条款', '隐私政策'] as $title) {
            $this->assertStringContainsString($title, $html, "页脚缺少 {$title}");
        }
    }

    public function test_register_requires_agreeing_terms(): void
    {
        $view = (string) file_get_contents($this->root() . '/resources/views/auth/register.blade.php');
        $this->assertStringContainsString('name="agree_terms"', $view);
        $this->assertStringContainsString("url('p/terms')", $view);
        $this->assertStringContainsString("url('p/privacy')", $view);

        $code = (string) file_get_contents($this->root() . '/app/Http/Controllers/AuthController.php');
        $this->assertStringContainsString("Request::input('agree_terms', '') !== '1'", $code);
        $this->assertStringContainsString('请先阅读并同意服务条款', $code);
        $this->assertStringContainsString("Setting::bool('register_agree_terms', true)", $code);
    }

    public function test_agree_terms_is_admin_toggleable(): void
    {
        $settings = (string) file_get_contents($this->root() . '/app/Core/Setting.php');
        $this->assertStringContainsString("'register_agree_terms' => '1'", $settings);
        $ctrl = (string) file_get_contents($this->root() . '/app/Http/Controllers/Admin/SettingController.php');
        $this->assertStringContainsString("'register_agree_terms'", $ctrl);
        $view = (string) file_get_contents($this->root() . '/resources/views/admin/settings.blade.php');
        $this->assertStringContainsString('name="register_agree_terms"', $view);
    }
}
