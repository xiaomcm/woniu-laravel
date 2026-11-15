<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Core\Setting;
use App\Drivers\Result;

class CodeService
{
    private const TTL = 600;
    private const GAP = 60;
    private const MAX_TRY = 5;

    /**
     * 走站点默认的邮件 / 短信驱动发送验证码。
     */
    public static function send(string $channel, string $target, string $scene, ?int $groupId = null): Result
    {
        return self::sendWith($channel, $target, $scene, static function (string $code) use ($channel, $target, $groupId): Result {
            if ($channel === 'mail') {
                if (!Setting::bool('mail_enabled', false)) {
                    return Result::fail('站点未开启邮件服务');
                }
                return MailService::sendCode($target, $code, $groupId);
            }

            if (!Setting::bool('sms_enabled', false)) {
                return Result::fail('站点未开启短信服务');
            }
            $driver = DriveService::sms($groupId);
            if ($driver === null) {
                return Result::fail('没有可用的短信驱动器');
            }
            return $driver->send($target, $code);
        });
    }

    /**
     * 通用发送通道：限流、生成 6 位码、投递、写会话都由这里统一处理。
     *
     * $deliver 接收生成好的验证码并返回一个 Result，调用方可以注入自己的
     * 短信驱动（例如注册验证码里按后台设置直接构造的腾讯云 / 阿里云短信）。
     */
    public static function sendWith(string $channel, string $target, string $scene, callable $deliver): Result
    {
        $target = trim($target);
        if ($target === '') {
            return Result::fail($channel === 'mail' ? '请填写邮箱' : '请填写手机号');
        }

        $bucket = self::bucket($channel, $scene, $target);
        $last = Session::get('_codes.' . $bucket . '.sent_at', 0);
        if (time() - (int) $last < self::GAP) {
            return Result::fail('发送过于频繁，请 ' . (self::GAP - (time() - (int) $last)) . ' 秒后再试');
        }

        $code = (string) random_int(100000, 999999);
        $result = $deliver($code);
        if (!$result instanceof Result) {
            return Result::fail('验证码发送器返回异常');
        }
        if (!$result->pass) {
            return $result;
        }

        Session::put('_codes.' . $bucket, [
            'code'    => $code,
            'expires' => time() + self::TTL,
            'sent_at' => time(),
            'tries'   => 0,
        ]);

        return Result::pass('验证码已发送');
    }

    public static function verify(string $channel, string $target, string $scene, string $code): bool
    {
        $bucket = self::bucket($channel, $scene, trim($target));
        $entry = Session::get('_codes.' . $bucket);
        if (!is_array($entry)) {
            return false;
        }
        if ((int) $entry['expires'] < time()) {
            Session::forget('_codes.' . $bucket);
            return false;
        }
        if ((int) $entry['tries'] >= self::MAX_TRY) {
            Session::forget('_codes.' . $bucket);
            return false;
        }

        Session::put('_codes.' . $bucket . '.tries', (int) $entry['tries'] + 1);

        if (!hash_equals((string) $entry['code'], trim($code))) {
            return false;
        }

        Session::forget('_codes.' . $bucket);
        return true;
    }

    private static function bucket(string $channel, string $scene, string $target): string
    {
        return $channel . ':' . $scene . ':' . md5(strtolower($target));
    }
}
