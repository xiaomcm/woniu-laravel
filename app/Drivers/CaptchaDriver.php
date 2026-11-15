<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers;

/**
 * 注册验证码驱动基类。
 *
 * 与其它驱动器不同，验证码参数集中在后台「系统设置」里（全局开关 + 服务商 + 验证方式），
 * 由 CaptchaService 直接按 settings 构造实例，不占用 drivers 表。
 */
abstract class CaptchaDriver extends Driver
{
    public function type(): string
    {
        return 'captcha';
    }

    /** 当前验证方式：image（本地图形） / slide（滑块） / sms（短信验证码） */
    abstract public function mode(): string;

    /** 注册页渲染所需参数，必须含 kind 字段 */
    abstract public function frontend(): array;

    /** 校验注册表单提交的验证码数据 */
    abstract public function verify(array $input): Result;
}
