<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 套餐有效天数契约测试。
 *
 * 语义：duration_days = 0 表示「永久有效（不限制）」，
 * 与 capacity / max_file_size / daily_limit 的「0 为不限」保持一致。
 *
 * 这里锁五件事：
 *   1. 后台保存允许 0（不能再被 max(1, ...) 钳成 1 天）
 *   2. 履约时 0 天套餐的到期时间写 NULL（永不过期）
 *   3. 展示层 0 天显示「永久」，不是「0 天」
 *   4. 过期回收只处理非 NULL 的到期时间，永久套餐不会被降级
 *   5. 降级链路真的接得上，不能是一段没人调用的死代码
 */
class PackageDurationContractTest extends TestCase
{
    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * 后台保存套餐时，duration_days 必须允许 0。
     */
    public function test_admin_accepts_zero_duration_days(): void
    {
        $shopCode = (string) file_get_contents(
            $this->projectRoot() . '/app/Http/Controllers/Admin/ShopController.php'
        );

        foreach ([['本仓库', $shopCode]] as [$label, $code]) {
            $this->assertStringContainsString(
                "max(0, Request::int('duration_days'",
                $code,
                "{$label} 的后台保存把 duration_days 钳成了最小 1，0 存不进去"
            );
            // 旧的 max(1, ...) 写法必须已经清除
            $this->assertStringNotContainsString(
                "max(1, Request::int('duration_days'",
                $code,
                "{$label} 仍有 max(1, Request::int('duration_days'"
            );
        }
    }

    /**
     * 履约时 0 天套餐的到期时间必须是 NULL，而不是算出某个日期。
     */
    public function test_fulfill_writes_null_expiry_for_permanent_package(): void
    {
        foreach ([
            ['本仓库', $this->projectRoot() . '/app/Services/PaymentService.php'],
        ] as [$label, $file]) {
            $code = (string) file_get_contents($file);
            $this->assertStringContainsString(
                '$days <= 0',
                $code,
                "{$label} 的履约逻辑没有区分 0 天（永久）套餐"
            );
            $this->assertStringContainsString(
                '$expire = null;',
                $code,
                "{$label} 的履约逻辑在 0 天时没有把到期时间写成 NULL"
            );
            // 不能再用 max(1, ...) 兜底
            $this->assertStringNotContainsString(
                "max(1, (int) \$package['duration_days'])",
                $code,
                "{$label} 的履约逻辑仍用 max(1, ...) 兜底，0 天会被算成 1 天"
            );
        }
    }

    /**
     * 展示层：0 天显示「永久」。
     */
    public function test_views_show_permanent_for_zero_duration(): void
    {
        // 本仓库 Blade 视图
        $views = [
            'admin/packages.blade.php' => '时长列',
            'shop.blade.php'           => '价格后缀',
            'checkout.blade.php'       => '有效期',
        ];
        foreach ($views as $file => $label) {
            $code = (string) file_get_contents($this->projectRoot() . "/resources/views/{$file}");
            $this->assertStringContainsString(
                "'永久'",
                $code,
                "本仓库的 {$file}（{$label}）没有对 0 天做「永久」处理"
            );
        }
    }

    /**
     * 过期回收只处理 package_expired_at 非空的用户，永久套餐天然不在范围内。
     */
    public function test_expiry_recycle_only_touches_non_null_expiry(): void
    {
        foreach ([
            ['本仓库', $this->projectRoot() . '/app/Services/PaymentService.php'],
        ] as [$label, $file]) {
            $code = (string) file_get_contents($file);
            $this->assertStringContainsString(
                'package_expired_at IS NOT NULL',
                $code,
                "{$label} 的过期回收没有排除 NULL 到期时间，永久套餐会被误降级"
            );
        }
    }

    /**
     * 套餐到期的降级链路必须真的接得上，不能是一段没人调用的死代码。
     *
     * expirePackages() 曾被写成死方法，套餐到期不会降级。
     */
    public function test_expiry_downgrade_is_wired_to_a_real_trigger(): void
    {
        foreach ([
            ['本仓库', $this->projectRoot() . '/app/Core/Auth.php'],
        ] as [$label, $file]) {
            $code = (string) file_get_contents($file);
            $this->assertStringContainsString(
                'expirePackageIfNeeded',
                $code,
                "{$label} 的登录态读取没有接入套餐到期降级（expirePackageIfNeeded）"
            );
            // 降级方法必须真的被调用，不能只是定义
            $this->assertGreaterThan(
                1,
                preg_match_all('/expirePackageIfNeeded/', $code),
                "{$label} 的 expirePackageIfNeeded 只定义了没有调用"
            );
        }
    }

    /**
     * Package::cast() 必须派生出 is_permanent / duration_text，
     * 供视图与 API 统一使用，避免各处重复判断。
     */
    public function test_package_cast_exposes_permanent_flags(): void
    {
        foreach ([
            ['本仓库', $this->projectRoot() . '/app/Models/Package.php'],
        ] as [$label, $file]) {
            $code = (string) file_get_contents($file);
            $this->assertStringContainsString(
                'is_permanent',
                $code,
                "{$label} 的 Package::cast() 没有派生 is_permanent"
            );
            $this->assertStringContainsString(
                'duration_text',
                $code,
                "{$label} 的 Package::cast() 没有派生 duration_text"
            );
        }
    }

    /**
     * API 输出里要能识别永久套餐。
     */
    public function test_api_package_payload_exposes_permanent_flag(): void
    {
        foreach ([
            ['本仓库', $this->projectRoot() . '/app/Http/Controllers/ApiController.php'],
        ] as [$label, $file]) {
            $code = (string) file_get_contents($file);
            $this->assertStringContainsString(
                "'is_permanent'",
                $code,
                "{$label} 的 API 套餐输出缺少 is_permanent 字段"
            );
            $this->assertStringContainsString(
                "'duration_text'",
                $code,
                "{$label} 的 API 套餐输出缺少 duration_text 字段"
            );
        }
    }
}
