<?php

namespace Tests\Unit;

use App\Core\Setting;
use App\Drivers\Captcha\AliyunCaptcha;
use App\Drivers\Captcha\LocalCaptcha;
use App\Drivers\Captcha\TencentCaptcha;
use PHPUnit\Framework\TestCase;

/**
 * 注册验证码驱动契约。
 *
 * 三种服务商、两种云厂商形态（滑块 / 短信）必须能被正确构造，
 * 空票据 / 空验证码必须在触网之前就被挡掉并返回明确提示。
 */
class CaptchaDriverContractTest extends TestCase
{
    public function test_setting_defaults_cover_all_captcha_keys(): void
    {
        $defaults = Setting::defaults();
        $required = [
            'captcha_enabled', 'captcha_provider', 'captcha_mode', 'captcha_local_length',
            'captcha_tencent_app_id', 'captcha_tencent_app_secret',
            'captcha_tencent_secret_id', 'captcha_tencent_secret_key',
            'captcha_tencent_sdk_app_id', 'captcha_tencent_sign_name',
            'captcha_tencent_template_id', 'captcha_tencent_region',
            'captcha_aliyun_access_key_id', 'captcha_aliyun_access_key_secret',
            'captcha_aliyun_scene_id', 'captcha_aliyun_prefix',
            'captcha_aliyun_endpoint', 'captcha_aliyun_region',
            'captcha_aliyun_sign_name', 'captcha_aliyun_template_code',
        ];
        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $defaults, "设置默认值缺少 {$key}");
        }
        $this->assertSame('local', $defaults['captcha_provider']);
        $this->assertSame('image', $defaults['captcha_mode']);
    }

    public function test_local_captcha_renders_svg(): void
    {
        $driver = new LocalCaptcha(['length' => '4']);
        $svg = $driver->svg('AB12');

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('<text', $svg);
        $this->assertStringContainsString('>A</text>', $svg);
        $this->assertStringContainsString('>B</text>', $svg);
        $this->assertStringContainsString('>1</text>', $svg);
        $this->assertStringContainsString('>2</text>', $svg);
        $this->assertSame('captcha', $driver->type());
        $this->assertSame('image', $driver->mode());
        $this->assertSame('image', $driver->frontend()['kind']);
        $this->assertSame(4, $driver->frontend()['length']);
    }

    public function test_local_captcha_rejects_empty_code(): void
    {
        $result = (new LocalCaptcha())->verify(['captcha_code' => '']);
        $this->assertFalse($result->pass);
        $this->assertStringContainsString('请填写', $result->message);
    }

    public function test_tencent_slide_and_sms_frontends(): void
    {
        $slide = new TencentCaptcha(['mode' => 'slide', 'app_id' => '2000000001']);
        $this->assertSame('slide', $slide->mode());
        $this->assertSame('tencent', $slide->frontend()['provider']);
        $this->assertSame('slide', $slide->frontend()['kind']);
        $this->assertSame('2000000001', $slide->frontend()['app_id']);

        $sms = new TencentCaptcha(['mode' => 'sms']);
        $this->assertSame('sms', $sms->mode());
        $this->assertSame('sms', $sms->frontend()['kind']);
        $this->assertSame('tencent', $sms->frontend()['provider']);
        $this->assertInstanceOf(\App\Drivers\Sms\TencentSms::class, $sms->smsDriver());
    }

    public function test_aliyun_slide_and_sms_frontends(): void
    {
        $slide = new AliyunCaptcha([
            'mode'     => 'slide',
            'scene_id' => 'scene-1',
            'prefix'   => 'pref-1',
        ]);
        $this->assertSame('slide', $slide->mode());
        $this->assertSame('aliyun', $slide->frontend()['provider']);
        $this->assertSame('scene-1', $slide->frontend()['scene_id']);
        $this->assertSame('pref-1', $slide->frontend()['prefix']);

        $sms = new AliyunCaptcha(['mode' => 'sms']);
        $this->assertSame('sms', $sms->mode());
        $this->assertSame('sms', $sms->frontend()['kind']);
        $this->assertInstanceOf(\App\Drivers\Sms\AliyunSms::class, $sms->smsDriver());
    }

    public function test_slide_verification_requires_ticket_before_any_network_call(): void
    {
        $tencent = (new TencentCaptcha(['mode' => 'slide']))->verify([]);
        $this->assertFalse($tencent->pass);
        $this->assertStringContainsString('请先完成滑块验证', $tencent->message);

        $aliyun = (new AliyunCaptcha(['mode' => 'slide']))->verify([]);
        $this->assertFalse($aliyun->pass);
        $this->assertStringContainsString('请先完成滑块验证', $aliyun->message);
    }

    public function test_sms_verification_requires_code(): void
    {
        $tencent = (new TencentCaptcha(['mode' => 'sms']))->verify(['phone' => '13800138000']);
        $this->assertFalse($tencent->pass);
        $this->assertStringContainsString('短信验证码', $tencent->message);

        $aliyun = (new AliyunCaptcha(['mode' => 'sms']))->verify(['phone' => '13800138000']);
        $this->assertFalse($aliyun->pass);
        $this->assertStringContainsString('短信验证码', $aliyun->message);
    }
}
