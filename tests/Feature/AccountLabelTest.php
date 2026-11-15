<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\DatabaseTestCase;

/**
 * 用户下拉菜单头部副标题：显示账号绑定的邮箱。
 *
 * 头部两行结构不变：
 *   第一行 strong = 昵称
 *   第二行 span   = 账号绑定的邮箱（原是「角色 · 邮箱」，其中 admin 账号的
 *                  email 列存的是用户名 "admin"，显示出来是「管理员 · admin」，
 *                  既不是邮箱、也跟上方昵称重复）
 *
 * 这里锁四件事：
 *   1. account_label() 的取值规则：合法邮箱直接显示，脏值/缺失回退用户名
 *   2. 三个布局（admin / app / site）都改用 account_label()，旧的拼接写法消失
 *   3. reset_accounts.php 脚本不再把用户名写进 email 列（脏数据源头）
 *   4. 改邮箱不影响用用户名登录（User::findForLogin 邮箱查不到会回退用户名）
 */
class AccountLabelTest extends DatabaseTestCase
{
    /**
     * @return array<string, string>
     */
    private function layoutFiles(): array
    {
        $root = dirname(__DIR__, 2) . '/resources/views/layouts';

        return [
            'admin' => $root . '/admin.blade.php',
            'app'   => $root . '/app.blade.php',
            'site'  => $root . '/site.blade.php',
        ];
    }

    // ---------------------------------------------------------------
    // account_label() 的取值规则
    // ---------------------------------------------------------------

    public function test_label_shows_bound_email(): void
    {
        $this->assertSame(
            'user@example.com',
            account_label(['name' => 'nick', 'email' => 'user@example.com'])
        );
    }

    public function test_label_falls_back_to_name_when_email_is_not_a_mailbox(): void
    {
        // 历史脏数据：email 列存的是用户名
        $this->assertSame('admin', account_label(['name' => 'admin', 'email' => 'admin']));
        $this->assertSame('admin', account_label(['name' => 'admin', 'email' => 'not-an-email']));
        $this->assertSame('admin', account_label(['name' => 'admin', 'email' => 'admin@']));
    }

    public function test_label_falls_back_to_name_when_email_is_missing(): void
    {
        $this->assertSame('nick', account_label(['name' => 'nick']));
        $this->assertSame('nick', account_label(['name' => 'nick', 'email' => '']));
        $this->assertSame('nick', account_label(['name' => 'nick', 'email' => '   ']));
    }

    public function test_label_handles_null_or_empty_user(): void
    {
        $this->assertSame('', account_label(null));
        $this->assertSame('', account_label([]));
    }

    public function test_role_label_distinguishes_admin_and_normal_user(): void
    {
        $this->assertSame('管理员', account_role(['is_admin' => 1]));
        $this->assertSame('普通用户', account_role(['is_admin' => 0]));
        $this->assertSame('普通用户', account_role([]));
    }

    // ---------------------------------------------------------------
    // 三个布局的渲染契约
    // ---------------------------------------------------------------

    public function test_all_layouts_render_account_label(): void
    {
        foreach ($this->layoutFiles() as $label => $file) {
            $this->assertFileExists($file, "{$label} 布局不存在");

            $blade = (string) file_get_contents($file);

            $this->assertStringContainsString(
                'account_label($me)',
                $blade,
                "{$label} 布局的头部副标题没有改用 account_label()，显示的还是角色+邮箱拼接"
            );
        }
    }

    public function test_all_layouts_keep_role_in_title_attribute(): void
    {
        // 角色信息没丢，只是从正文挪到了悬停提示
        foreach ($this->layoutFiles() as $label => $file) {
            $blade = (string) file_get_contents($file);

            $this->assertStringContainsString(
                'account_role($me)',
                $blade,
                "{$label} 布局没有保留角色提示，副标题改成邮箱后角色信息会彻底丢失"
            );
        }
    }

    public function test_all_layouts_dropped_the_old_role_and_email_concat(): void
    {
        foreach ($this->layoutFiles() as $label => $file) {
            $blade = (string) file_get_contents($file);

            $this->assertStringNotContainsString(
                "' . e((string) \$me['email'])",
                $blade,
                "{$label} 布局还在用旧的「角色 · 邮箱」拼接写法"
            );
            $this->assertStringNotContainsString(
                "\$isAdminUser ? '管理员'",
                $blade,
                "{$label} 布局还在用旧的 \$isAdminUser 角色拼接写法"
            );
            $this->assertStringNotContainsString(
                "\$siteIsAdmin ? '管理员'",
                $blade,
                "{$label} 布局还在用旧的 \$siteIsAdmin 角色拼接写法"
            );
        }
    }

    // ---------------------------------------------------------------
    // 脏数据源头：重置账号脚本
    // ---------------------------------------------------------------

    public function test_reset_accounts_script_writes_a_real_mailbox(): void
    {
        $script = dirname(__DIR__, 2) . '/tools/reset_accounts.php';
        $this->assertFileExists($script);

        $code = (string) file_get_contents($script);

        $this->assertMatchesRegularExpression(
            '/\$loginEmail\s*=\s*\'([^\']+)\'/',
            $code,
            'reset_accounts.php 没有单独定义登录邮箱变量'
        );

        preg_match('/\$loginEmail\s*=\s*\'([^\']+)\'/', $code, $m);
        $this->assertNotFalse(
            filter_var($m[1], FILTER_VALIDATE_EMAIL),
            "reset_accounts.php 写入的邮箱 {$m[1]} 不是合法邮箱格式"
        );

        // 不能再出现「用户名当邮箱写」的老写法
        $this->assertStringNotContainsString(
            '$stmt->execute([$loginName, $loginName, $hash]);',
            $code,
            'reset_accounts.php 还在把用户名写进 email 列，这是「管理员 · admin」的根源'
        );
    }

    // ---------------------------------------------------------------
    // 登录兼容：改了邮箱仍能用用户名登录
    // ---------------------------------------------------------------

    public function test_login_by_username_still_works_after_email_change(): void
    {
        $id = $this->createUser('admin', 'admin@woniu.local', 'admin', true);

        $byName = User::findForLogin('admin');
        $this->assertNotNull($byName, '按用户名查不到用户');
        $this->assertSame($id, (int) $byName['id']);

        $byEmail = User::findForLogin('admin@woniu.local');
        $this->assertNotNull($byEmail, '按邮箱查不到用户');
        $this->assertSame($id, (int) $byEmail['id']);
    }

    public function test_login_by_username_falls_back_when_email_column_is_dirty(): void
    {
        // 邮箱列存着用户名的脏数据账号，也要能用用户名登录
        $id = $this->createUser('root', 'root', 'Test123456', true);

        $found = User::findForLogin('root');
        $this->assertNotNull($found);
        $this->assertSame($id, (int) $found['id']);
    }
}
