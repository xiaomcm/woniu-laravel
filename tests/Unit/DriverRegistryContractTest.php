<?php

namespace Tests\Unit;

use App\Drivers\Factory;
use App\Drivers\Registry;
use App\Drivers\SecurityDriver;
use App\Drivers\PaymentDriver;
use App\Drivers\SmsDriver;
use App\Models\Storage as StorageModel;
use App\Storage\Driver as StorageDriver;
use App\Storage\Manager;
use PHPUnit\Framework\TestCase;

/**
 * 驱动注册表契约测试。
 *
 * 本仓库补齐了大量新增驱动：审核（腾讯云 / 百度 / NSFW.js）、
 * 支付（PayPal / Stripe / 银联）、短信（华为云 / 京东云 / 容联 / Submail / Twilio）、
 * 存储（SFTP / MinIO）。这些驱动必须：
 *   1. 在 Registry 里有登记（后台才出配置表单）；
 *   2. Factory::supports() 返回 true；
 *   3. 能用 Factory / Manager 实例化出对应基类对象。
 *
 * 一旦有人删了注册项或类名写错，这个测试立刻红 —— 比上线后才发现「后台选不了某个驱动」强。
 */
class DriverRegistryContractTest extends TestCase
{
    /**
     * 新增审核驱动：QQ号 34995549 这一版补齐的腾讯云 / 百度 / NSFW.js。
     */
    public function test_new_audit_drivers_are_registered_and_instantiable(): void
    {
        foreach (['tencent_ims', 'baidu_censor', 'nsfwjs'] as $provider) {
            $this->assertNotNull(
                Registry::provider('security', $provider),
                "Registry 缺少 security/$provider 登记项"
            );
            $this->assertTrue(
                Factory::supports('security', $provider),
                "Factory 不支持 security/$provider"
            );

            $driver = Factory::make([
                'type'     => 'security',
                'provider' => $provider,
                'config'   => '{}',
            ]);
            $this->assertInstanceOf(SecurityDriver::class, $driver, "security/$provider 实例化失败");
        }
    }

    /**
     * 新增支付驱动：PayPal / Stripe / 银联。
     */
    public function test_new_payment_drivers_are_registered_and_instantiable(): void
    {
        foreach (['paypal', 'stripe', 'unionpay'] as $provider) {
            $this->assertNotNull(Registry::provider('payment', $provider));
            $this->assertTrue(Factory::supports('payment', $provider));

            $driver = Factory::make([
                'type'     => 'payment',
                'provider' => $provider,
                'config'   => '{}',
            ]);
            $this->assertInstanceOf(PaymentDriver::class, $driver, "payment/$provider 实例化失败");
        }
    }

    /**
     * 新增短信驱动：华为云 / 京东云 / 容联 / Submail / Twilio。
     */
    public function test_new_sms_drivers_are_registered_and_instantiable(): void
    {
        foreach (['huawei_sms', 'jd_sms', 'ronglian_sms', 'submail_sms', 'twilio_sms'] as $provider) {
            $this->assertNotNull(Registry::provider('sms', $provider));
            $this->assertTrue(Factory::supports('sms', $provider));

            $driver = Factory::make([
                'type'     => 'sms',
                'provider' => $provider,
                'config'   => '{}',
            ]);
            $this->assertInstanceOf(SmsDriver::class, $driver, "sms/$provider 实例化失败");
        }
    }

    /**
     * 新增存储驱动：SFTP / MinIO。
     * 存储走的是 Storage\Model 的 type 清单 + Manager::$map，不走 Registry。
     */
    public function test_new_storage_drivers_are_wired_and_instantiable(): void
    {
        $types = StorageModel::types();
        foreach (['sftp', 'minio'] as $type) {
            $this->assertArrayHasKey($type, $types, 'Storage 类型清单缺少该类型（后台下拉出不来）');

            $driver = Manager::make([
                'type'   => $type,
                'config' => '{}',
            ]);
            $this->assertInstanceOf(StorageDriver::class, $driver, '存储实例化失败：' . $type);
        }
    }
}
