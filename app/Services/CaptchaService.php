<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Core\Setting;
use App\Drivers\Captcha\AliyunCaptcha;
use App\Drivers\Captcha\LocalCaptcha;
use App\Drivers\Captcha\TencentCaptcha;
use App\Drivers\CaptchaDriver;
use App\Drivers\Result;

/**
 * 注册验证码统一入口。
 *
 * 后台「系统设置」里配置：captcha_enabled / captcha_provider / captcha_mode
 * 以及各家密钥。这里负责把 settings 映射成驱动实例，注册流程只跟本类打交道。
 */
class CaptchaService
{
    public static function enabled(): bool
    {
        return Setting::bool('captcha_enabled', false);
    }

    public static function provider(): string
    {
        $provider = (string) Setting::get('captcha_provider', 'local');
        return in_array($provider, ['local', 'tencent', 'aliyun'], true) ? $provider : 'local';
    }

    /** 按当前设置构造验证码驱动；本地类型固定为图形模式 */
    public static function driver(): ?CaptchaDriver
    {
        $config = self::config();
        switch (self::provider()) {
            case 'tencent':
                return new TencentCaptcha($config);
            case 'aliyun':
                return new AliyunCaptcha($config);
            default:
                return new LocalCaptcha($config);
        }
    }

    /** 注册页渲染所需信息 */
    public static function frontend(): array
    {
        if (!self::enabled()) {
            return ['enabled' => false, 'kind' => 'none', 'provider' => ''];
        }
        $driver = self::driver();
        if ($driver === null) {
            return ['enabled' => false, 'kind' => 'none', 'provider' => ''];
        }
        return array_merge([
            'enabled'  => true,
            'provider' => self::provider(),
        ], $driver->frontend());
    }

    /** 校验注册提交；全局开关关闭时直接放行 */
    public static function verify(array $input): Result
    {
        if (!self::enabled()) {
            return Result::pass('未启用验证码');
        }
        $driver = self::driver();
        if ($driver === null) {
            return Result::fail('验证码驱动不可用');
        }
        $input['_ip'] = Request::ip();
        try {
            return $driver->verify($input);
        } catch (\Throwable $e) {
            return Result::fail('验证码校验异常：' . $e->getMessage());
        }
    }

    /** 生成本地图形验证码 SVG；非图形模式返回 null */
    public static function image(): ?string
    {
        $meta = self::frontend();
        if ($meta['kind'] !== 'image') {
            return null;
        }
        $driver = self::driver();
        if (!$driver instanceof LocalCaptcha) {
            return null;
        }
        return $driver->svg($driver->issue());
    }

    /** 云厂商短信验证模式下发 6 位验证码 */
    public static function sendSms(string $phone): Result
    {
        $phone = trim($phone);
        if (!self::enabled() || self::frontend()['kind'] !== 'sms') {
            return Result::fail('当前未启用短信验证码');
        }
        if (!preg_match('/^\+?[0-9][0-9\-]{5,19}$/', $phone)) {
            return Result::fail('请填写正确的手机号');
        }

        $driver = self::driver();
        if ($driver instanceof TencentCaptcha) {
            $sms = $driver->smsDriver();
        } elseif ($driver instanceof AliyunCaptcha) {
            $sms = $driver->smsDriver();
        } else {
            return Result::fail('当前验证码类型不支持短信');
        }

        return CodeService::sendWith(
            'sms',
            $phone,
            'register_captcha',
            static fn (string $code): Result => $sms->send($phone, $code)
        );
    }

    /** settings 里的长键名映射成驱动内部使用的短键 */
    private static function config(): array
    {
        $s = Setting::all();
        $g = static fn (string $key, string $default = ''): string =>
            (string) (($s[$key] ?? null) === null || ($s[$key] ?? '') === '' ? $default : $s[$key]);

        return [
            'mode'    => $g('captcha_mode', 'image'),
            'length'  => $g('captcha_local_length', '4'),

            // 腾讯云
            'app_id'      => $g('captcha_tencent_app_id'),
            'app_secret'  => $g('captcha_tencent_app_secret'),
            'secret_id'   => $g('captcha_tencent_secret_id'),
            'secret_key'  => $g('captcha_tencent_secret_key'),
            'sdk_app_id'  => $g('captcha_tencent_sdk_app_id'),
            'sign_name'   => $g('captcha_tencent_sign_name'),
            'template_id' => $g('captcha_tencent_template_id'),
            'region'      => $g('captcha_tencent_region', 'ap-guangzhou'),

            // 阿里云（同名键共用 access_key_* / region；这里显式拆开避免串味）
            'access_key_id'     => $g('captcha_aliyun_access_key_id'),
            'access_key_secret' => $g('captcha_aliyun_access_key_secret'),
            'scene_id'          => $g('captcha_aliyun_scene_id'),
            'prefix'            => $g('captcha_aliyun_prefix'),
            'endpoint'          => $g('captcha_aliyun_endpoint', 'captcha.cn-shanghai.aliyuncs.com'),
            'aliyun_sign_name'  => $g('captcha_aliyun_sign_name'),
            'aliyun_template'   => $g('captcha_aliyun_template_code'),
            'aliyun_region'     => $g('captcha_aliyun_region', 'cn-hangzhou'),
        ];
    }
}
