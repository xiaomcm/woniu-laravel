<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * SEO 与 API 开关契约测试。
 *
 * 用户提的问题：后台只能设置站名，没有副标题、SEO 介绍、关键词，
 * 等于「没给 SEO 留后路」。这里锁四件事：
 *   1. 设置默认值里有 site_subtitle / site_seo_description / site_keywords
 *   2. 后台设置页有这三个输入框，且保存白名单收得住（不然填了也存不进去）
 *   3. 落地页 layout 真的把 description / keywords / og 标签输出到 HTML
 *   4. API 有全局开关（api_enabled）和用户级开关（users.api_enabled），
 *      且认证入口两处都检查
 */
class SeoMetaContractTest extends TestCase
{
    private function viewFile(string $rel): string
    {
        $file = dirname(__DIR__, 2) . '/resources/views/' . $rel;
        $this->assertFileExists($file, "视图缺失: $rel");
        return (string) file_get_contents($file);
    }

    private function src(string $rel): string
    {
        $file = dirname(__DIR__, 2) . '/' . $rel;
        $this->assertFileExists($file, "文件缺失: $rel");
        return (string) file_get_contents($file);
    }

    public function test_seo_settings_have_defaults(): void
    {
        $code = $this->src('app/Core/Setting.php');
        foreach (['site_subtitle', 'site_seo_description', 'site_keywords'] as $key) {
            $this->assertStringContainsString(
                "'$key'",
                $code,
                '设置默认值里缺少 ' . $key . '，后台就存不住这个字段'
            );
        }
    }

    public function test_admin_settings_page_has_seo_inputs(): void
    {
        $code = $this->viewFile('admin/settings.blade.php');
        foreach (['site_subtitle', 'site_seo_description', 'site_keywords'] as $key) {
            $this->assertStringContainsString(
                'name="' . $key . '"',
                $code,
                "后台设置页缺少 $key 输入框"
            );
        }
    }

    public function test_admin_save_whitelist_accepts_seo_keys(): void
    {
        $code = $this->src('app/Http/Controllers/Admin/SettingController.php');
        foreach (['site_subtitle', 'site_seo_description', 'site_keywords'] as $key) {
            $this->assertStringContainsString(
                "'$key'",
                $code,
                '保存白名单没有 ' . $key . '，表单填了也存不进去'
            );
        }
    }

    public function test_landing_layout_outputs_seo_meta(): void
    {
        $code = $this->viewFile('layouts/landing.blade.php');
        $this->assertStringContainsString('name="description"', $code, '落地页没有 description');
        $this->assertStringContainsString('name="keywords"', $code, '落地页没有 keywords');
        $this->assertStringContainsString('property="og:title"', $code, '落地页没有 og:title');
        $this->assertStringContainsString('site_subtitle', $code, '标题没有拼副标题');
    }

    public function test_site_layout_outputs_seo_meta(): void
    {
        $code = $this->viewFile('layouts/site.blade.php');
        $this->assertStringContainsString('name="description"', $code, '前台内页没有 description');
        $this->assertStringContainsString('name="keywords"', $code, '前台内页没有 keywords');
    }

    public function test_console_is_noindex(): void
    {
        $code = $this->viewFile('layouts/app.blade.php');
        $this->assertStringContainsString(
            'noindex',
            $code,
            '登录后的控制台页应加 noindex，避免被搜索引擎收录'
        );
    }

    public function test_api_has_global_and_user_switches(): void
    {
        // 全局开关：设置项存在，且默认开启
        $this->assertStringContainsString("'api_enabled'", $this->src('app/Core/Setting.php'));

        // 用户级开关：三处建表/升级路径都要有，漏一处就会出现「新装库里没这个字段」
        $this->assertStringContainsString(
            'api_enabled',
            $this->src('app/Core/SchemaDef.php'),
            'users 表定义（PG/SQL Server）里缺少 api_enabled'
        );
        $this->assertStringContainsString(
            'api_enabled',
            $this->src('app/Core/Schema.php'),
            'users 建表语句（SQLite/MySQL）里缺少 api_enabled'
        );
        $this->assertStringContainsString(
            "'api_enabled'",
            $this->src('app/Core/Migrator.php'),
            '老库升级里缺少 users.api_enabled'
        );

        // 认证入口两个分支都要检查
        $api = $this->src('app/Http/Controllers/ApiController.php');
        $this->assertStringContainsString("Setting::bool('api_enabled'", $api, 'API 认证没有检查全局开关');
        $this->assertStringContainsString('该账号已关闭 API 接口', $api, 'API 认证没有检查用户级开关');
    }

    public function test_api_docs_page_exists(): void
    {
        $this->assertFileExists(
            dirname(__DIR__, 2) . '/resources/views/api-docs.blade.php',
            'API 文档页视图缺失'
        );
        $routes = $this->src('routes/web.php');
        $this->assertStringContainsString('/api-docs', $routes, 'API 文档页路由缺失');
    }
}
