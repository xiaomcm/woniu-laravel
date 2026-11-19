<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Core;

class Setting
{
    private static array $items = [];
    private static bool $loaded = false;

    public static function defaults(): array
    {
        return [


            'site_name'        => '蜗牛图床',
            'site_description' => '简单、快速、稳定的图床程序',
            // SEO 三件套：副标题拼在标题后面，介绍与关键词进 meta 标签
            // subtitle 留空时标题只显示站名；seo_description 留空时回落到 site_description
            'site_subtitle'    => '',
            'site_seo_description' => '',
            'site_keywords'    => '',
            'site_url'         => '',
            'site_logo'        => '',
            'site_favicon'     => '',
            'site_icp'         => '',
            'site_footer'      => 'Powered by WONIU',

            // 后台左上角的名称。留空 = 跟随站点名（显示「{站点名} 后台」）
            'admin_title'      => '',


            'register_enabled' => '1',
            'register_invite'  => '',
            'register_email_verify' => '0',
            // 注册时必须勾选同意服务条款（默认开）
            'register_agree_terms' => '1',
            'default_group_id' => '1',

            // 夜间模式定时切换：默认关闭（尊重访客/system 偏好），开启后按时间自动深浅色
            'theme_auto_enabled' => '0',
            'theme_dark_start'   => '22:00',
            'theme_dark_end'     => '07:00',

            // 注册验证码：全局开关 + 服务商 + 模式（image / slide / sms）
            'captcha_enabled'  => '0',
            'captcha_provider' => 'local',
            'captcha_mode'     => 'image',
            'captcha_local_length' => '4',
            'captcha_tencent_app_id'      => '',
            'captcha_tencent_app_secret'  => '',
            'captcha_tencent_secret_id'   => '',
            'captcha_tencent_secret_key'  => '',
            'captcha_tencent_sdk_app_id'  => '',
            'captcha_tencent_sign_name'   => '',
            'captcha_tencent_template_id' => '',
            'captcha_tencent_region'      => 'ap-guangzhou',
            'captcha_aliyun_access_key_id'     => '',
            'captcha_aliyun_access_key_secret' => '',
            'captcha_aliyun_scene_id'          => '',
            'captcha_aliyun_prefix'            => '',
            'captcha_aliyun_endpoint'          => 'captcha.cn-shanghai.aliyuncs.com',
            'captcha_aliyun_region'            => 'cn-hangzhou',
            'captcha_aliyun_sign_name'         => '',
            'captcha_aliyun_template_code'     => '',
            'review_enabled'   => '0',
            'guest_upload'     => '0',


            'upload_max_size'  => '5242880',
            'upload_allowed'   => 'jpg,jpeg,png,gif,bmp,webp,svg,ico',
            'upload_path_format' => '{Y}/{m}/{d}/{sha1}.{ext}',
            'upload_dedupe'    => '1',
            'upload_quality'   => '92',
            'upload_max_width' => '0',
            'upload_rename'    => 'sha1',


            'thumb_enabled'    => '1',
            'thumb_width'      => '360',
            // 灯箱预览：详情页点图全屏看，列表页悬停出放大按钮
            'lightbox_enabled'  => '1',
            // 图片懒加载：列表/瀑布流里的图滚到视口附近才下载，首屏不再一次性拉全量
            'lazy_load_enabled' => '1',


            'watermark_enabled' => '0',
            'watermark_type'   => 'text',
            'watermark_text'   => '',
            'watermark_image'  => '',
            'watermark_position' => 'bottom-right',
            'watermark_opacity' => '45',
            'watermark_size'   => '18',


            'hotlink_enabled'  => '0',
            'hotlink_domains'  => '',
            // 改邮箱要不要验证码：默认要，关掉才退回无需验证的旧行为
            'email_change_verify' => '1',
            'api_enabled'      => '1',
            'page_size'        => '24',
            // 账号自动退出：无操作多少秒后强制登出，0 为不限制，上限 1800 秒（30 分钟）
            'session_ttl'      => '0',

            'explore_enabled'   => '1',
            'user_home_enabled' => '1',
            'download_enabled'  => '1',
            'notice_enabled'    => '0',
            'site_notice'       => '',
        ];
    }

    public static function boot(): void
    {
        self::load();
    }

    /**
     * 清空进程内的设置缓存。
     *
     * 设置是「一次读入、全进程复用」的设计（每个请求只查一次库）。
     * 单元测试里换库或改设置后要调这个，否则会读到上一轮的旧值。
     */
    public static function reset(): void
    {
        self::$items = [];
        self::$loaded = false;
    }

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;
        self::$items = self::defaults();
        try {
            $rows = Database::fetchAll('SELECT `key`, `value` FROM ' . Database::table('settings'));
            foreach ($rows as $row) {
                self::$items[(string) $row['key']] = $row['value'];
            }
        } catch (\Throwable $e) {


        }
    }

    public static function all(): array
    {
        self::load();
        return self::$items;
    }

    public static function get(string $key, $default = null)
    {
        self::load();
        if (!array_key_exists($key, self::$items)) {
            return $default;
        }
        $value = self::$items[$key];
        return $value === null || $value === '' ? $default : $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, null);
        if ($value === null) {
            return $default;
        }
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, null);
        return $value === null ? $default : (int) $value;
    }

    public static function set(string $key, $value): void
    {
        self::load();
        $exists = Database::fetch(
            'SELECT `key` FROM ' . Database::table('settings') . ' WHERE `key` = :k',
            ['k' => $key]
        );
        if ($exists !== null) {
            Database::update('settings', ['value' => (string) $value], '`key` = :k', ['k' => $key]);
        } else {
            Database::insert('settings', ['key' => $key, 'value' => (string) $value]);
        }
        self::$items[$key] = (string) $value;
    }

    public static function setMany(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            self::set((string) $key, is_array($value) ? implode(',', $value) : $value);
        }
    }
}
