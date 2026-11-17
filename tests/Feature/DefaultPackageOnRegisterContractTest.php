<?php

namespace Tests\Feature;

use App\Core\Database as DB;
use App\Core\Setting;
use App\Models\Group;
use App\Models\Package;
use App\Models\User;
use App\Services\PaymentService;
use Tests\TestCase;

/**
 * 新用户注册绑定默认套餐契约。
 * 锁住：注册入口调用、第三方登录调用、共用开通方法、后台设置项、以及真实绑定效果。
 */
class DefaultPackageOnRegisterContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_register_and_oauth_both_apply_default_package(): void
    {
        $auth = (string) file_get_contents($this->root() . '/app/Http/Controllers/AuthController.php');
        $oauth = (string) file_get_contents($this->root() . '/app/Http/Controllers/OauthController.php');
        $this->assertStringContainsString('applyDefaultPackage', $auth);
        $this->assertStringContainsString('default_package_id', $auth);
        $this->assertStringContainsString('applyDefaultPackage', $oauth, '第三方登录新用户也要绑默认套餐');
    }

    public function test_grant_method_is_shared_by_purchase_and_register(): void
    {
        $pay = (string) file_get_contents($this->root() . '/app/Services/PaymentService.php');
        $this->assertStringContainsString('function grantPackage(', $pay);
        // fulfill（买套餐）和注册都要走 grantPackage，权益才一致
        $this->assertStringContainsString('self::grantPackage(', $pay);
        $this->assertStringContainsString('renewFromExisting', $pay);
        // 注册入口确实调了它
        $auth = (string) file_get_contents($this->root() . '/app/Http/Controllers/AuthController.php');
        $this->assertStringContainsString('PaymentService::grantPackage(', $auth);
    }

    public function test_settings_page_uses_dropdown_with_register_hint(): void
    {
        $view = (string) file_get_contents($this->root() . '/resources/views/admin/settings.blade.php');
        $this->assertStringContainsString('name="default_package_id"', $view);
        $this->assertStringContainsString('新用户默认套餐', $view);
        $this->assertStringContainsString('注册后自动绑定', $view);
        $this->assertStringContainsString('不自动绑定', $view);
    }

    public function test_grant_package_writes_group_capacity_and_package_id(): void
    {
        // 建一个临时用户和套餐，验证绑定后字段被正确写入，用完清理
        $group = Group::defaultGroup();
        $groupId = $group === null ? 1 : (int) $group['id'];
        $pkgId = Package::create([
            'name'          => '契约测试临时套餐',
            'group_id'      => $groupId,
            'price_cents'   => 0,
            'duration_days' => 30,
            'capacity'      => 123456789,
            'status'        => 1,
            'sortable'      => 0,
        ]);
        $userId = User::create([
            'name'     => 'contract_tmp_user',
            'email'    => 'contract_tmp_user@test.local',
            'password' => 'x',
            'group_id' => $groupId,
        ]);
        try {
            $pkg = Package::find($pkgId);
            $user = User::find($userId);
            $this->assertNotNull($pkg);
            $this->assertNotNull($user);
            PaymentService::grantPackage($user, $pkg, false);

            $after = User::find($userId);
            $this->assertNotNull($after);
            $this->assertSame($pkgId, (int) $after['package_id'], 'package_id 应写入');
            $this->assertSame(123456789, (int) $after['capacity'], '容量应写入套餐值');
            $this->assertSame($groupId, (int) $after['group_id'], '用户组应写入套餐绑定组');
            $this->assertNotEmpty((string) $after['package_expired_at'], '有有效期的套餐应写到期时间');
        } finally {
            User::deleteById($userId);
            Package::deleteById($pkgId);
        }
    }

    public function test_apply_default_package_skips_when_disabled(): void
    {
        // 关掉默认套餐（设为 0）时不改用户
        $group = Group::defaultGroup();
        $groupId = $group === null ? 1 : (int) $group['id'];
        $userId = User::create([
            'name'     => 'contract_skip_user',
            'email'    => 'contract_skip_user@test.local',
            'password' => 'x',
            'group_id' => $groupId,
        ]);
        try {
            Setting::set('default_package_id', '0');
            \App\Http\Controllers\AuthController::applyDefaultPackage($userId);
            $after = User::find($userId);
            $this->assertNotNull($after);
            $this->assertEmpty($after['package_id'] ?? null, '设为 0 时不应绑定任何套餐');
        } finally {
            Setting::reset();
            User::deleteById($userId);
        }
    }
}
