<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 套餐的单文件/每日张数必须读「绑定用户组」的配置。
 *
 * 背景：packages 表虽然有 max_file_size / daily_limit 两列，但买套餐后
 * 只把 capacity 和 group_id 写进用户，上传校验读的是用户组。所以那两列
 * 填了不生效。现在后台不再填、展示层一律读组，锁死这条规则防止回退。
 */
class PackageGroupLimitsContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_admin_package_form_does_not_ask_for_dead_fields(): void
    {
        $view = (string) file_get_contents($this->root() . '/resources/views/admin/packages.blade.php');
        // 表单里不该再有这两个输入框
        $this->assertStringNotContainsString('name="max_file_size_mb"', $view);
        $this->assertStringNotContainsString('name="daily_limit"', $view);
        // 但要告诉管理员去用户组里设
        $this->assertStringContainsString('由下方', $view);
        $this->assertStringContainsString('用户组', $view);
    }

    public function test_admin_save_no_longer_reads_dead_fields(): void
    {
        $code = (string) file_get_contents(
            $this->root() . '/app/Http/Controllers/Admin/ShopController.php'
        );
        $this->assertStringNotContainsString("Request::input('max_file_size_mb'", $code);
        $this->assertStringNotContainsString("Request::int('daily_limit'", $code);
        // 列保留写 0，兼容旧库（用正则容忍对齐空格）
        $this->assertMatchesRegularExpression("/'max_file_size'\s*=>\s*0/", $code);
        $this->assertMatchesRegularExpression("/'daily_limit'\s*=>\s*0/", $code);
    }

    public function test_front_pages_read_limits_from_bound_group(): void
    {
        $shop = (string) file_get_contents($this->root() . '/resources/views/shop.blade.php');
        $checkout = (string) file_get_contents($this->root() . '/resources/views/checkout.blade.php');
        foreach (['shop' => $shop, 'checkout' => $checkout] as $name => $code) {
            $this->assertStringContainsString('$groupLimits', $code, "{$name} 页没传用户组上限");
            $this->assertStringNotContainsString(
                '$package[\'max_file_size\']',
                $code,
                "{$name} 页还在读套餐自己的单文件大小（不生效的值）"
            );
            $this->assertStringNotContainsString(
                '$package[\'daily_limit\']',
                $code,
                "{$name} 页还在读套餐自己的每日张数（不生效的值）"
            );
        }
    }

    public function test_api_reads_limits_from_bound_group(): void
    {
        $code = (string) file_get_contents($this->root() . '/app/Http/Controllers/ApiController.php');
        // packagePayload 里改成查组再取值
        $this->assertStringContainsString('Group::find((int) ($package[\'group_id\'] ?? 0))', $code);
        $this->assertStringContainsString('\'max_file_size\'  => $maxFileSize', $code);
        $this->assertStringContainsString('\'daily_limit\'    => $dailyLimit', $code);
    }

    public function test_shop_controller_builds_group_limit_map(): void
    {
        $code = (string) file_get_contents($this->root() . '/app/Http/Controllers/ShopController.php');
        $this->assertStringContainsString('function groupLimits()', $code);
        $this->assertStringContainsString('Group::allRows()', $code);
        // index 和 checkout 两处都要传
        $this->assertSame(2, substr_count($code, "'groupLimits'"));
    }

    public function test_group_benefits_method_covers_all_entitlements(): void
    {
        $code = (string) file_get_contents($this->root() . '/app/Models/Group.php');
        $this->assertStringContainsString('function benefits(', $code);
        // 权益清单要覆盖：单文件、每日、单次数量、格式、权限、水印
        $this->assertStringContainsString('单文件最大', $code);
        $this->assertStringContainsString('每日可传', $code);
        $this->assertStringContainsString('单次最多', $code);
        $this->assertStringContainsString('支持格式', $code);
        $this->assertStringContainsString('打包下载', $code);
    }

    public function test_front_pages_show_group_benefits(): void
    {
        foreach (['shop.blade.php', 'checkout.blade.php'] as $file) {
            $view = (string) file_get_contents($this->root() . '/resources/views/' . $file);
            $this->assertStringContainsString('benefits', $view, "{$file} 没展示用户组权益");
            $this->assertStringContainsString('升级', $view, "{$file} 没说明买后升级到哪个组");
        }
    }

    public function test_admin_package_form_shows_group_benefits_preview(): void
    {
        $view = (string) file_get_contents($this->root() . '/resources/views/admin/packages.blade.php');
        $this->assertStringContainsString('groupBenefits', $view);
        $this->assertStringContainsString('各组权益', $view);
        $code = (string) file_get_contents($this->root() . '/app/Http/Controllers/Admin/ShopController.php');
        $this->assertStringContainsString('Group::benefits($group)', $code);
    }

    public function test_group_benefits_returns_readable_strings(): void
    {
        // 用一个真实的组跑一遍 benefits，确认返回的是可读中文句子
        $group = \App\Models\Group::allRows()[0] ?? null;
        $this->assertNotNull($group);
        $benefits = \App\Models\Group::benefits($group);
        $this->assertIsArray($benefits);
        $this->assertGreaterThan(0, count($benefits));
        foreach ($benefits as $line) {
            $this->assertIsString($line);
            $this->assertNotSame('', trim($line));
        }
    }
}
