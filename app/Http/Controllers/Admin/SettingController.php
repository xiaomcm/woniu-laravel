<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Response as HttpResponse;
use App\Http\Controllers\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Setting;
use App\Models\Group;
use App\Models\Storage as StorageModel;

class SettingController extends Controller
{
    private const TEXT_KEYS = [
        'site_name', 'site_description', 'site_url', 'site_icp', 'site_footer',
        'admin_title',
        'register_invite', 'upload_allowed', 'upload_path_format',
        'watermark_type', 'watermark_text', 'watermark_position',
        'hotlink_domains', 'site_notice',
        'moderation_action', 'currency_symbol', 'mail_from', 'mail_from_name', 'sms_sign',

        // 注册验证码配置
        'captcha_provider', 'captcha_mode', 'captcha_local_length',
        'captcha_tencent_app_id', 'captcha_tencent_app_secret',
        'captcha_tencent_secret_id', 'captcha_tencent_secret_key',
        'captcha_tencent_sdk_app_id', 'captcha_tencent_sign_name',
        'captcha_tencent_template_id', 'captcha_tencent_region',
        'captcha_aliyun_access_key_id', 'captcha_aliyun_access_key_secret',
        'captcha_aliyun_scene_id', 'captcha_aliyun_prefix',
        'captcha_aliyun_endpoint', 'captcha_aliyun_region',
        'captcha_aliyun_sign_name', 'captcha_aliyun_template_code',
    ];

    private const NUMBER_KEYS = [
        'default_group_id', 'upload_quality', 'upload_max_width',
        'thumb_width', 'watermark_opacity', 'watermark_size', 'page_size',
        'share_password_ttl', 'order_expire_minutes', 'default_package_id',
    ];

    private const BOOL_KEYS = [
        'register_enabled', 'register_email_verify', 'review_enabled', 'guest_upload', 'upload_dedupe',
        'thumb_enabled', 'lightbox_enabled', 'watermark_enabled', 'hotlink_enabled', 'api_enabled',
        'lazy_load_enabled',
        'explore_enabled', 'user_home_enabled', 'download_enabled', 'notice_enabled',
        'shop_enabled', 'ticket_enabled', 'report_enabled', 'feedback_enabled',
        'cloud_process_enabled', 'mail_enabled', 'sms_enabled', 'oauth_enabled',
        'coupon_enabled', 'captcha_enabled',
    ];

    public function index(): HttpResponse
    {
        Auth::requireAdmin();
        $mailDriver = \App\Services\DriveService::mail();
        return $this->view('admin/settings', [
            'pageTitle'     => '系统设置',
            'settings'      => Setting::all(),
            'groups'        => Group::allRows(),
            'storages'      => StorageModel::allRows(),
            'mailDriverName' => $mailDriver !== null ? $mailDriver->name() : '',
        ], 'layouts/admin');
    }

    public function save(): void
    {
        $this->validateCsrf();
        Auth::requireAdmin();

        $pairs = [];
        foreach (self::TEXT_KEYS as $key) {
            $value = Request::input($key);
            if ($value !== null) {
                $pairs[$key] = (string) $value;
            }
        }
        foreach (self::NUMBER_KEYS as $key) {
            $value = Request::input($key);
            if ($value !== null) {
                $pairs[$key] = (string) max(0, (int) $value);
            }
        }
        foreach (self::BOOL_KEYS as $key) {
            $pairs[$key] = Request::bool($key, false) ? '1' : '0';
        }



        $maxSizeMb = Request::input('upload_max_size_mb');
        if ($maxSizeMb !== null) {
            $pairs['upload_max_size'] = (string) max(0, (int) round((float) $maxSizeMb * 1024 * 1024));
        }

        // 账号自动退出：数字 + 单位（秒/分钟）换算成秒，最大 30 分钟
        $ttlValue = Request::input('session_ttl_value');
        if ($ttlValue !== null) {
            $ttl = max(0, (int) $ttlValue);
            if (Request::input('session_ttl_unit') === 'min') {
                $ttl *= 60;
            }
            // 封顶 30 分钟，防止管理员把全站账号锁死在秒级
            $pairs['session_ttl'] = (string) min(1800, $ttl);
        }

        // 验证码类型与模式做白名单收敛：本地只有图形，云厂商只有滑块 / 短信
        $provider = (string) ($pairs['captcha_provider'] ?? 'local');
        if (!in_array($provider, ['local', 'tencent', 'aliyun'], true)) {
            $provider = 'local';
        }
        $mode = (string) ($pairs['captcha_mode'] ?? 'image');
        if ($provider === 'local') {
            $mode = 'image';
        } elseif (!in_array($mode, ['slide', 'sms'], true)) {
            $mode = 'slide';
        }
        $pairs['captcha_provider'] = $provider;
        $pairs['captcha_mode'] = $mode;
        $pairs['captcha_local_length'] = (string) max(3, min(8, (int) ($pairs['captcha_local_length'] ?? 4)));

        Setting::setMany($pairs);
        flash('success', '设置已保存');
        redirect('/admin/settings');
    }

    /**
     * 邮件设置里的「测试发信」：用当前已保存的邮件开关 + 邮件驱动真实投递一封邮件。
     * 与「驱动器」页的按 ID 测试不同，这里验证的是站点实际采用的默认配置。
     */
    public function mailTest(): void
    {
        $this->validateCsrf();
        Auth::requireAdmin();

        $to = strtolower(trim((string) Request::input('to', '')));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->api(false, '请填写正确的收件邮箱', null, 422);
        }
        if (!Setting::bool('mail_enabled', false)) {
            $this->api(false, '邮件服务未启用，请先勾选「启用邮件」并保存', null, 400);
        }
        if (\App\Services\DriveService::mail() === null) {
            $this->api(false, '没有可用的邮件驱动器，请先在「驱动器」里添加并启用一个邮件驱动', null, 400);
        }

        $siteName = (string) Setting::get('site_name', '蜗牛图床');
        $body = '<p>这是一封来自 ' . e($siteName) . ' 的配置测试邮件。</p>'
            . '<p>收到本邮件即表示当前 SMTP / 邮件接口配置有效。发送时间：' . e(date('Y-m-d H:i:s')) . '</p>';

        try {
            $result = \App\Services\MailService::sendTemplate($to, $siteName . ' · 测试发信', $body);
        } catch (\Throwable $e) {
            $this->api(false, '发信异常：' . $e->getMessage(), null, 500);
        }

        $this->api(
            $result->pass,
            $result->message !== '' ? $result->message : ($result->pass ? '测试邮件已投递，请查收收件箱' : '发送失败'),
            null,
            $result->pass ? 200 : 400
        );
    }
}
