<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Core;

use PDO;

class Migrator
{
    private const VERSION = 9;

    private const TARGET = [
        'images' => [
            'description' => ['sqlite' => 'VARCHAR(500) NULL', 'mysql' => 'VARCHAR(500) NULL DEFAULT NULL'],
            'tags'        => ['sqlite' => 'VARCHAR(255) NULL', 'mysql' => 'VARCHAR(255) NULL DEFAULT NULL'],
            'views'       => ['sqlite' => 'INTEGER NOT NULL DEFAULT 0', 'mysql' => 'INT UNSIGNED NOT NULL DEFAULT 0'],
            'password'    => ['sqlite' => 'VARCHAR(255) NULL', 'mysql' => 'VARCHAR(255) NULL DEFAULT NULL'],
            'review_note' => ['sqlite' => 'VARCHAR(255) NULL', 'mysql' => 'VARCHAR(255) NULL DEFAULT NULL'],
        ],
        'groups' => [
            'daily_limit' => ['sqlite' => 'INTEGER NOT NULL DEFAULT 0', 'mysql' => 'INT NOT NULL DEFAULT 0'],
            'permissions' => ['sqlite' => 'TEXT NULL', 'mysql' => 'TEXT NULL'],
            'buyable'     => ['sqlite' => 'INTEGER NOT NULL DEFAULT 0', 'mysql' => 'TINYINT(1) NOT NULL DEFAULT 0'],
        ],
        'users' => [
            'group_expired_at'   => ['sqlite' => 'DATETIME NULL', 'mysql' => 'DATETIME NULL DEFAULT NULL'],
            'package_id'         => ['sqlite' => 'INTEGER NULL', 'mysql' => 'INT UNSIGNED NULL DEFAULT NULL'],
            'package_expired_at' => ['sqlite' => 'DATETIME NULL', 'mysql' => 'DATETIME NULL DEFAULT NULL'],
            // 注册短信验证码模式下需要保存手机号
            'phone'              => ['sqlite' => 'VARCHAR(32) NULL', 'mysql' => 'VARCHAR(32) NULL DEFAULT NULL'],
            // 用户自己的 API 开关，存量用户默认开启（1）
            'api_enabled'        => ['sqlite' => 'INTEGER NOT NULL DEFAULT 1', 'mysql' => 'TINYINT(1) NOT NULL DEFAULT 1'],
        ],
        'albums' => [
            'password' => ['sqlite' => 'VARCHAR(255) NULL', 'mysql' => 'VARCHAR(255) NULL DEFAULT NULL'],
            'views'    => ['sqlite' => 'INTEGER NOT NULL DEFAULT 0', 'mysql' => 'INT UNSIGNED NOT NULL DEFAULT 0'],
        ],
        'notices' => [
            // 公告是否以全站弹窗展示
            'popup' => ['sqlite' => 'INTEGER NOT NULL DEFAULT 0', 'mysql' => 'TINYINT(1) NOT NULL DEFAULT 0'],
        ],
        'packages' => [
            // 每个用户可购买该套餐的次数上限，0 = 不限购
            'purchase_limit' => ['sqlite' => 'INTEGER NOT NULL DEFAULT 0', 'mysql' => 'INT NOT NULL DEFAULT 0'],
        ],
    ];

    private const EXTRA_SETTINGS = [
        'explore_enabled'      => '1',
        'explore_review'       => '1',
        'user_home_enabled'    => '1',
        'download_enabled'     => '1',
        'site_notice'          => '',
        'notice_enabled'       => '0',
        'report_enabled'       => '1',
        'feedback_enabled'     => '1',
        'ticket_enabled'       => '1',
        'shop_enabled'         => '0',
        'coupon_enabled'       => '1',
        'oauth_enabled'        => '0',
        'oauth_auto_register'  => '1',
        'share_password_ttl'   => '7200',
        'mail_enabled'         => '0',
        'sms_enabled'          => '0',
        'register_verify_mail' => '0',
        'register_verify_sms'  => '0',
        'moderation_action'    => 'hold',
        'default_package_id'   => '1',
        'order_expire_minutes' => '30',
        'currency_symbol'      => '¥',
        'invoice_note'         => '',
        'reg_gift_capacity'    => '0',
        'cloud_process_enabled' => '1',
        'coupon_stack_limit'   => '1',
        'mail_from'            => '',
        'mail_from_name'       => '',
        'sms_sign'             => '',
        'captcha_enabled'      => '0',
        'captcha_provider'     => 'local',
        'captcha_mode'         => 'image',
        'captcha_local_length' => '4',
        'captcha_tencent_region' => 'ap-guangzhou',
        'captcha_aliyun_endpoint' => 'captcha.cn-shanghai.aliyuncs.com',
        'captcha_aliyun_region'   => 'cn-hangzhou',
        // API 全局开关：0 = 全站所有账号的 API 都停用
        'api_enabled'         => '1',
        // SEO：副标题、SEO 介绍、关键词
        'site_subtitle'        => '',
        'site_seo_description' => '',
        'site_keywords'        => '',
    ];

    public static function run(): void
    {
        try {
            $pdo = Database::pdo();
        } catch (\Throwable $e) {
            return;
        }
        if ($pdo === null) {
            return;
        }

        $current = (int) (Setting::get('schema_version', '0') ?: 0);
        if ($current >= self::VERSION) {
            return;
        }

        $driver = Database::driver();
        $prefix = Database::prefix();

        foreach (SchemaExtra::tables($driver, $prefix) as $sql) {
            try {
                $pdo->exec($sql);
            } catch (\Throwable $e) {
            }
        }

        foreach (self::TARGET as $table => $columns) {
            $existing = self::columns($pdo, $prefix . $table, $driver);
            if ($existing === []) {
                continue;
            }
            foreach ($columns as $column => $types) {
                if (in_array(strtolower($column), $existing, true)) {
                    continue;
                }
                $type = $types[$driver === 'sqlite' ? 'sqlite' : 'mysql'];
                try {
                    $pdo->exec('ALTER TABLE `' . $prefix . $table . '` ADD COLUMN `' . $column . '` ' . $type);
                } catch (\Throwable $e) {
                }
            }
        }

        foreach (self::EXTRA_SETTINGS as $key => $value) {
            try {
                $exists = Database::fetch(
                    'SELECT `key` FROM ' . Database::table('settings') . ' WHERE `key` = :k',
                    ['k' => $key]
                );
                if ($exists === null) {
                    Database::insert('settings', ['key' => $key, 'value' => $value]);
                    Setting::set($key, $value);
                }
            } catch (\Throwable $e) {
            }
        }

        self::seedIfEmpty($pdo, $prefix, $driver);
        self::linkPivot($pdo, $prefix);

        // settings 的主键就是 key，没有自增 id，先查后写才能保证版本号真正落库
        try {
            $exists = Database::fetch(
                'SELECT `key` FROM ' . Database::table('settings') . ' WHERE `key` = :k',
                ['k' => 'schema_version']
            );
            if ($exists === null) {
                Database::insert('settings', ['key' => 'schema_version', 'value' => (string) self::VERSION]);
            } else {
                Database::update(
                    'settings',
                    ['value' => (string) self::VERSION],
                    '`key` = :k',
                    ['k' => 'schema_version']
                );
            }
            Setting::set('schema_version', (string) self::VERSION);
        } catch (\Throwable $e) {
        }
    }

    private static function seedIfEmpty(PDO $pdo, string $prefix, string $driver): void
    {
        foreach (['drivers', 'packages', 'notices', 'pages'] as $table) {
            try {
                $count = (int) $pdo->query('SELECT COUNT(*) FROM `' . $prefix . $table . '`')->fetchColumn();
            } catch (\Throwable $e) {
                continue;
            }
            if ($count > 0) {
                continue;
            }
            foreach (SchemaExtra::seeds($driver, $prefix) as $seed) {
                if (strpos($seed['sql'], '`' . $prefix . $table . '`') === false) {
                    continue;
                }
                try {
                    $pdo->prepare($seed['sql'])->execute($seed['args']);
                } catch (\Throwable $e) {
                }
            }
        }
    }

    private static function linkPivot(PDO $pdo, string $prefix): void
    {
        try {
            $pdo->exec('INSERT INTO `' . $prefix . 'group_storage` (`group_id`,`storage_id`)
                        SELECT g.`id`, s.`id` FROM `' . $prefix . 'groups` g, `' . $prefix . 'storages` s
                        WHERE NOT EXISTS (
                            SELECT 1 FROM `' . $prefix . 'group_storage` x WHERE x.`group_id` = g.`id`
                        ) AND s.`is_default` = 1');
        } catch (\Throwable $e) {
        }
    }

    private static function columns(PDO $pdo, string $table, string $driver): array
    {
        $columns = [];
        try {
            if ($driver === 'sqlite') {
                $rows = $pdo->query('PRAGMA table_info(`' . $table . '`)')->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $columns[] = strtolower((string) $row['name']);
                }
            } else {
                $rows = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $columns[] = strtolower((string) $row['Field']);
                }
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $columns;
    }
}
