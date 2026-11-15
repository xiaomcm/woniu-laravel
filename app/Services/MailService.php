<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Services;

use App\Core\Setting;
use App\Drivers\Result;

class MailService
{
    public static function send(string $to, string $subject, string $html, ?int $groupId = null): Result
    {
        if (!Setting::bool('mail_enabled', false)) {
            return Result::fail('邮件服务未启用');
        }
        $driver = DriveService::mail($groupId);
        if ($driver === null) {
            return Result::fail('没有可用的邮件驱动器');
        }
        return $driver->send($to, $subject, $html);
    }

    public static function sendTemplate(string $to, string $title, string $bodyHtml, ?int $groupId = null): Result
    {
        $siteName = (string) Setting::get('site_name', '蜗牛图床');
        $siteUrl = (string) Setting::get('site_url', '');
        $html = '<div style="max-width:560px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;background:#f6f7fb;padding:28px">'
            . '<div style="background:#fff;border-radius:14px;padding:28px;border:1px solid #e8ecf4">'
            . '<div style="font-size:17px;font-weight:700;color:#111827;margin-bottom:14px">' . e($title) . '</div>'
            . '<div style="font-size:14px;line-height:1.8;color:#374151">' . $bodyHtml . '</div>'
            . '<div style="margin-top:22px;padding-top:16px;border-top:1px solid #eef1f7;font-size:12px;color:#9aa3b2">'
            . e($siteName) . ($siteUrl !== '' ? ' · <a href="' . e($siteUrl) . '" style="color:#5b7cfa;text-decoration:none">' . e($siteUrl) . '</a>' : '')
            . '</div></div></div>';

        return self::send($to, $title, $html, $groupId);
    }

    public static function sendCode(string $to, string $code, ?int $groupId = null): Result
    {
        $body = '<p>你正在进行身份验证，验证码：</p>'
            . '<p style="font-size:26px;font-weight:700;letter-spacing:6px;color:#111827;margin:14px 0">' . e($code) . '</p>'
            . '<p style="color:#6b7280;font-size:13px">10 分钟内有效。如果这不是你本人的操作，忽略此邮件即可。</p>';
        return self::sendTemplate($to, '你的验证码', $body, $groupId);
    }
}
