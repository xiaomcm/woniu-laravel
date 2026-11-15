<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 套餐限购（purchase_limit）契约测试。
 *
 * 用户需求：一个套餐可以限制每个用户只能购买 1 次 / 2 次等，
 * 这个限制要在「新建套餐」里能填，并且真正拦住下单。
 *
 * 字段语义：packages.purchase_limit，0 = 不限购，N = 每个用户最多买 N 次
 * （与 duration_days / capacity / daily_limit 的「0 为不限」语义一致）。
 *
 * 这里锁住六件事：
 *   1. 迁移脚本给 packages 表补 purchase_limit 列（sqlite + mysql 都要有）
 *   2. 建表语句（SchemaExtra）里也有这一列，新装的库不会缺
 *   3. Package::cast() 派生出 is_limit_purchase / purchase_limit_text
 *   4. 后台能存能取：packagePayload 读 purchase_limit，表单有输入框
 *   5. 服务端真的拦得住：quote() 里有限购校验，且 Order::purchasedCount 有被调用
 *   6. 计数口径正确：已支付占位 + 未过期待支付占位
 */
class PackagePurchaseLimitContractTest extends TestCase
{
    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * 本仓库关键文件相对路径。
     *
     * @return array<string, array<string, string>>
     */
    private function targets(): array
    {
        return [
            '本仓库' => [
                'migrator' => $this->projectRoot() . '/app/Core/Migrator.php',
                'schema'   => $this->projectRoot() . '/app/Core/SchemaExtra.php',
                'package'  => $this->projectRoot() . '/app/Models/Package.php',
                'order'    => $this->projectRoot() . '/app/Models/Order.php',
                'payment'  => $this->projectRoot() . '/app/Services/PaymentService.php',
                'admin'    => $this->projectRoot() . '/app/Http/Controllers/Admin/ShopController.php',
                'shop'     => $this->projectRoot() . '/app/Http/Controllers/ShopController.php',
                'api'      => $this->projectRoot() . '/app/Http/Controllers/ApiController.php',
                'view'     => $this->projectRoot() . '/resources/views/admin/packages.blade.php',
            ],
        ];
    }

    public function test_migrator_adds_purchase_limit_column(): void
    {
        foreach ($this->targets() as $label => $files) {
            $code = (string) file_get_contents($files['migrator']);

            // 直接截取 packages 段来做子串判断，避免复杂正则触发 PCRE 回溯上限
            $pos = strpos($code, "'packages' => [");
            $this->assertNotFalse(
                $pos,
                "{$label} 的 Migrator TARGET 里没有 packages 段"
            );
            $segment = substr($code, (int) $pos, 400);

            $this->assertStringContainsString(
                "'purchase_limit' => [",
                $segment,
                "{$label} 的 Migrator TARGET 里没有 packages.purchase_limit，老库升级后不会补这一列"
            );
            $this->assertStringContainsString(
                "'sqlite' => 'INTEGER NOT NULL DEFAULT 0'",
                $segment,
                "{$label} 的 purchase_limit 列类型应为 INTEGER NOT NULL DEFAULT 0"
            );
            $this->assertStringContainsString(
                "'mysql' => 'INT NOT NULL DEFAULT 0'",
                $segment,
                "{$label} 的 purchase_limit 缺少 mysql 版本定义"
            );
        }
    }

    public function test_schema_extra_create_table_includes_purchase_limit(): void
    {
        foreach ($this->targets() as $label => $files) {
            $code = (string) file_get_contents($files['schema']);

            // 建表语句（sqlite 与 mysql 两份）都要有这一列
            $this->assertGreaterThanOrEqual(
                2,
                preg_match_all('/`purchase_limit`\s+(INTEGER|INT)\s+NOT NULL DEFAULT 0/', $code),
                "{$label} 的 SchemaExtra 建表语句缺 purchase_limit（sqlite / mysql 都要有）"
            );
        }
    }

    public function test_package_cast_exposes_limit_fields(): void
    {
        foreach ($this->targets() as $label => $files) {
            $code = (string) file_get_contents($files['package']);

            $this->assertStringContainsString(
                "\$row['purchase_limit'] = (int) (\$row['purchase_limit'] ?? 0);",
                $code,
                "{$label} 的 Package::cast 没把 purchase_limit 转成 int"
            );

            $this->assertStringContainsString(
                "\$row['is_limit_purchase']",
                $code,
                "{$label} 的 Package::cast 缺少 is_limit_purchase 派生字段"
            );

            $this->assertStringContainsString(
                "\$row['purchase_limit_text']",
                $code,
                "{$label} 的 Package::cast 缺少 purchase_limit_text 派生字段"
            );
        }
    }

    public function test_admin_can_save_purchase_limit(): void
    {
        foreach ($this->targets() as $label => $files) {
            $admin = (string) file_get_contents($files['admin']);
            $view = (string) file_get_contents($files['view']);

            $this->assertMatchesRegularExpression(
                '/\'purchase_limit\'\s*=>\s*max\(0,\s*Request::int\(\'purchase_limit\'/',
                $admin,
                "{$label} 的后台 packagePayload 没保存 purchase_limit（0 = 不限购）"
            );

            $this->assertMatchesRegularExpression(
                '/name="purchase_limit"/',
                $view,
                "{$label} 的后台套餐表单没有「限购次数」输入框"
            );

            $this->assertStringContainsString(
                '限购次数',
                $view,
                "{$label} 的后台套餐表单缺少「限购次数」标签"
            );
        }
    }

    public function test_quote_enforces_limit_and_counting_is_wired(): void
    {
        foreach ($this->targets() as $label => $files) {
            $payment = (string) file_get_contents($files['payment']);
            $order = (string) file_get_contents($files['order']);

            // quote() 里必须有限购校验
            $this->assertStringContainsString(
                'purchase_limit',
                $payment,
                "{$label} 的 PaymentService::quote 没读 purchase_limit"
            );

            $this->assertStringContainsString(
                'Order::purchasedCount',
                $payment,
                "{$label} 的 PaymentService::quote 没调用 Order::purchasedCount，限购不会生效"
            );

            $this->assertStringContainsString(
                '每人限购',
                $payment,
                "{$label} 的限购提示语缺失"
            );

            // purchasedCount 必须有定义且被调用（防止退化成死代码）
            $this->assertGreaterThan(
                1,
                preg_match_all('/purchasedCount/', $payment),
                "{$label} 的 purchasedCount 在支付服务里没有调用点"
            );

            $this->assertStringContainsString(
                'function purchasedCount',
                $order,
                "{$label} 的 Order 模型缺少 purchasedCount"
            );
        }
    }

    public function test_counting_treats_pending_orders_as_occupied_until_expired(): void
    {
        foreach ($this->targets() as $label => $files) {
            $code = (string) file_get_contents($files['order']);

            $this->assertStringContainsString(
                'STATUS_PAID',
                $code,
                "{$label} 的 purchasedCount 没统计已支付订单"
            );

            $this->assertStringContainsString(
                'STATUS_PENDING',
                $code,
                "{$label} 的 purchasedCount 没把未过期的待支付订单算作占位"
            );

            $this->assertStringContainsString(
                'expire_at',
                $code,
                "{$label} 的 purchasedCount 没按 expire_at 释放过期订单占的名额"
            );
        }
    }
}
