<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 系统设置页「更新检查 + 更新日志」区块契约。
 * 锁住：接口实时检查、日志跟随本地 CHANGELOG、区块在设置页底部、路由已登记。
 */
class UpdateSectionContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_check_update_endpoint_is_registered(): void
    {
        $routes = (string) file_get_contents($this->root() . '/tests/Fixtures/routes-2.0.txt');
        $this->assertStringContainsString('POST /admin/settings/check-update', $routes);
    }

    public function test_controller_checks_realtime_and_parses_local_changelog(): void
    {
        $code = (string) file_get_contents(
            $this->root() . '/app/Http/Controllers/Admin/SettingController.php'
        );
        // 检查更新走 UpdateChecker（实时），并把本地 CHANGELOG 解析后一起返回
        $this->assertStringContainsString('function checkUpdate()', $code);
        $this->assertStringContainsString('UpdateChecker::check()', $code);
        $this->assertStringContainsString('parseChangelog()', $code);
        $this->assertStringContainsString("BASE_PATH . '/CHANGELOG.md'", $code);
        // 接口只返回数据，不下发任何更新包
        $this->assertStringNotContainsString('browser_download_url', $code);
        $this->assertStringNotContainsString('ZipArchive', $code);
    }

    public function test_settings_page_has_update_section_at_bottom(): void
    {
        $view = (string) file_get_contents($this->root() . '/resources/views/admin/settings.blade.php');
        $this->assertStringContainsString('id="update-check-card"', $view);
        $this->assertStringContainsString('id="update-check-btn"', $view);
        $this->assertStringContainsString('admin/settings/check-update', $view);
        $this->assertStringContainsString('当前版本', $view);
        $this->assertStringContainsString('更新日志', $view);
        // 区块在 </form> 之后，检查按钮不会误提交设置表单
        $this->assertGreaterThan(
            strpos($view, '</form>'),
            strpos($view, 'id="update-check-card"'),
            '更新区块必须放在表单外'
        );
    }

    public function test_index_passes_version_and_changelog_to_view(): void
    {
        $code = (string) file_get_contents(
            $this->root() . '/app/Http/Controllers/Admin/SettingController.php'
        );
        $this->assertStringContainsString("'currentVersion'", $code);
        $this->assertStringContainsString("'changelog'", $code);
    }

    public function test_changelog_shows_only_latest_version(): void
    {
        $code = (string) file_get_contents(
            $this->root() . '/app/Http/Controllers/Admin/SettingController.php'
        );
        // 默认只解析最新一条，不堆历史版本
        $this->assertStringContainsString('parseChangelog(int $limit = 1)', $code);
        $view = (string) file_get_contents($this->root() . '/resources/views/admin/settings.blade.php');
        $this->assertStringContainsString('$changelog[0]', $view);
        $this->assertStringNotContainsString('update-log-toggle', $view);
        $this->assertStringNotContainsString('展开更早版本', $view);
    }

    public function test_save_button_is_not_sticky(): void
    {
        $view = (string) file_get_contents($this->root() . '/resources/views/admin/settings.blade.php');
        // 用户要求保存按钮固定待在表单末尾，不再 sticky 浮动
        $pos = strpos($view, '保存全部设置');
        $lineStart = strrpos(substr($view, 0, $pos), "\n");
        $line = substr($view, $lineStart, $pos - $lineStart + 40);
        $this->assertStringNotContainsString('position:sticky', $line, '保存按钮不该是 sticky');
    }

    public function test_narrow_screen_sidebar_is_narrower(): void
    {
        $css = (string) file_get_contents($this->root() . '/public/assets/app.css');
        // 桌面侧栏收窄到 218px，窄屏抽屉 180px
        $this->assertStringContainsString('--sidebar-w: 218px', $css);
        $this->assertStringContainsString('width: 180px', $css);
        $this->assertStringNotContainsString('--sidebar-w: 248px', $css);
        $this->assertStringNotContainsString('width: 216px', $css);
    }

    public function test_desktop_sidebar_close_button_works(): void
    {
        // 桌面端也要能看到关闭 X，且点了能收成图标轨道（不再只处理抽屉）
        $css = (string) file_get_contents($this->root() . '/public/assets/app.css');
        $this->assertStringContainsString('.sidebar-close { display: inline-flex; }', $css);
        $this->assertStringContainsString('.shell.sidebar-collapsed .sidebar-close { display: none; }', $css);
        $js = (string) file_get_contents($this->root() . '/public/assets/app.js');
        $this->assertStringContainsString('if (WIDE.matches) {', $js);
        $this->assertStringContainsString("localStorage.setItem(SIDEBAR_KEY, 'collapsed');", $js);
        // Esc 只关窄屏抽屉，不该收起桌面常驻侧栏
        $this->assertStringContainsString("e.key === 'Escape' && !WIDE.matches", $js);
    }

    public function test_mobile_hides_front_sidebar_group_label(): void
    {
        // 手机端隐藏前台侧栏那个「图床」分组标题
        $view = (string) file_get_contents($this->root() . '/resources/views/layouts/app.blade.php');
        $this->assertStringContainsString('nav-label nav-label-mobile-hide">图床', $view);
        $css = (string) file_get_contents($this->root() . '/public/assets/app.css');
        $this->assertStringContainsString('.nav-label-mobile-hide { display: none; }', $css);
        // 后台的分组标题不该被这个 class 影响
        $admin = (string) file_get_contents($this->root() . '/resources/views/layouts/admin.blade.php');
        $this->assertStringNotContainsString('nav-label-mobile-hide', $admin);
    }
}
