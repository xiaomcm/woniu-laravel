<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 用户管理契约：默认管理员禁删禁停、普通用户可停用、停用账号登录专门提示。
 */
class UserManageContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_default_admin_cannot_be_deleted_or_disabled(): void
    {
        $code = (string) file_get_contents($this->root() . '/app/Http/Controllers/Admin/UserController.php');
        $this->assertStringContainsString('function defaultAdminId()', $code);
        $this->assertStringContainsString('ORDER BY id ASC LIMIT 1', $code);
        // delete 里拦默认管理员
        $this->assertStringContainsString('默认管理员不能删除', $code);
        // update 里拦停用和取消管理员
        $this->assertStringContainsString('默认管理员不能停用', $code);
        $this->assertStringContainsString('默认管理员不能取消管理员权限', $code);
        $this->assertStringContainsString('不能删除自己', $code);
    }

    public function test_disabled_account_gets_specific_login_message(): void
    {
        $code = (string) file_get_contents($this->root() . '/app/Http/Controllers/AuthController.php');
        $this->assertStringContainsString('该账号已被停用', $code);
        $this->assertStringContainsString('findForLogin', $code);
        // 只有密码正确才提示停用（避免泄露账号存在性）
        $this->assertStringContainsString('password_verify', $code);
        // 笼统提示里不再带"已被禁用"
        $this->assertStringNotContainsString('账号或密码错误，或账号已被禁用', $code);
    }

    public function test_user_list_has_disable_button_and_hides_default_admin_actions(): void
    {
        $view = (string) file_get_contents($this->root() . '/resources/views/admin/users.blade.php');
        $this->assertStringContainsString('defaultAdminId', $view);
        $this->assertMatchesRegularExpression("/'停用' : '启用'/", $view);
        $this->assertStringContainsString('已停用', $view);
        $this->assertStringContainsString('默认管理员', $view);
        $this->assertStringContainsString('@if (!$isDefaultAdmin)', $view, '默认管理员要隐藏删除/停用按钮');
    }

    public function test_index_passes_default_admin_id(): void
    {
        $code = (string) file_get_contents($this->root() . '/app/Http/Controllers/Admin/UserController.php');
        $this->assertStringContainsString("'defaultAdminId'", $code);
    }

    public function test_default_admin_query_orders_by_id(): void
    {
        // 逻辑用「id 最小的管理员」识别默认管理员，查询必须对且带 LIMIT 1。
        // 测试库未必有管理员，所以只验证 SQL 结构，不依赖数据。
        $code = (string) file_get_contents($this->root() . '/app/Http/Controllers/Admin/UserController.php');
        $this->assertMatchesRegularExpression(
            '/is_admin = 1 ORDER BY id ASC LIMIT 1/',
            $code
        );
        // 查不到管理员时 defaultAdminId 返回 0，不会误伤任何用户
        $this->assertStringContainsString('return $row === null ? 0', $code);
    }
}
