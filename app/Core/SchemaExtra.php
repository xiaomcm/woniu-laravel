<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Core;

class SchemaExtra
{
    public static function tables(string $driver, string $prefix = ''): array
    {
        if (SchemaDef::supports($driver)) {
            return SchemaDef::extraTables($driver, $prefix);
        }

        $p = $prefix;

        if ($driver === 'sqlite') {
            return [
                "CREATE TABLE IF NOT EXISTS `{$p}drivers` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `name` VARCHAR(64) NOT NULL,
                    `type` VARCHAR(16) NOT NULL,
                    `provider` VARCHAR(32) NOT NULL,
                    `config` TEXT NULL,
                    `status` INTEGER NOT NULL DEFAULT 1,
                    `sortable` INTEGER NOT NULL DEFAULT 0,
                    `remark` VARCHAR(255) NULL,
                    `created_at` DATETIME NULL,
                    `updated_at` DATETIME NULL
                )",
                "CREATE INDEX IF NOT EXISTS `{$p}drivers_type` ON `{$p}drivers` (`type`)",
                "CREATE TABLE IF NOT EXISTS `{$p}group_storage` (
                    `group_id` INTEGER NOT NULL,
                    `storage_id` INTEGER NOT NULL,
                    PRIMARY KEY (`group_id`,`storage_id`)
                )",
                "CREATE TABLE IF NOT EXISTS `{$p}group_driver` (
                    `group_id` INTEGER NOT NULL,
                    `driver_id` INTEGER NOT NULL,
                    PRIMARY KEY (`group_id`,`driver_id`)
                )",
                "CREATE TABLE IF NOT EXISTS `{$p}storage_driver` (
                    `storage_id` INTEGER NOT NULL,
                    `driver_id` INTEGER NOT NULL,
                    PRIMARY KEY (`storage_id`,`driver_id`)
                )",
                "CREATE TABLE IF NOT EXISTS `{$p}packages` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `name` VARCHAR(64) NOT NULL,
                    `group_id` INTEGER NOT NULL DEFAULT 1,
                    `price_cents` INTEGER NOT NULL DEFAULT 0,
                    `duration_days` INTEGER NOT NULL DEFAULT 30,
                    `capacity` INTEGER NOT NULL DEFAULT 0,
                    `max_file_size` INTEGER NOT NULL DEFAULT 0,
                    `daily_limit` INTEGER NOT NULL DEFAULT 0,
                    `purchase_limit` INTEGER NOT NULL DEFAULT 0,
                    `description` VARCHAR(500) NULL,
                    `features` TEXT NULL,
                    `status` INTEGER NOT NULL DEFAULT 1,
                    `sortable` INTEGER NOT NULL DEFAULT 0,
                    `created_at` DATETIME NULL,
                    `updated_at` DATETIME NULL
                )",
                "CREATE TABLE IF NOT EXISTS `{$p}orders` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `order_no` VARCHAR(32) NOT NULL,
                    `user_id` INTEGER NOT NULL,
                    `package_id` INTEGER NOT NULL,
                    `package_name` VARCHAR(64) NOT NULL,
                    `amount_cents` INTEGER NOT NULL DEFAULT 0,
                    `discount_cents` INTEGER NOT NULL DEFAULT 0,
                    `total_cents` INTEGER NOT NULL DEFAULT 0,
                    `coupon_id` INTEGER NULL,
                    `driver_id` INTEGER NULL,
                    `gateway` VARCHAR(32) NULL,
                    `trade_no` VARCHAR(80) NULL,
                    `status` INTEGER NOT NULL DEFAULT 0,
                    `paid_at` DATETIME NULL,
                    `expire_at` DATETIME NULL,
                    `created_at` DATETIME NULL,
                    `updated_at` DATETIME NULL
                )",
                "CREATE UNIQUE INDEX IF NOT EXISTS `{$p}orders_no` ON `{$p}orders` (`order_no`)",
                "CREATE INDEX IF NOT EXISTS `{$p}orders_user` ON `{$p}orders` (`user_id`)",
                "CREATE TABLE IF NOT EXISTS `{$p}coupons` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `code` VARCHAR(32) NOT NULL,
                    `name` VARCHAR(64) NOT NULL,
                    `type` VARCHAR(10) NOT NULL DEFAULT 'fixed',
                    `value` INTEGER NOT NULL DEFAULT 0,
                    `min_amount_cents` INTEGER NOT NULL DEFAULT 0,
                    `max_discount_cents` INTEGER NOT NULL DEFAULT 0,
                    `quota` INTEGER NOT NULL DEFAULT 0,
                    `used` INTEGER NOT NULL DEFAULT 0,
                    `per_user` INTEGER NOT NULL DEFAULT 1,
                    `scope` TEXT NULL,
                    `starts_at` DATETIME NULL,
                    `expires_at` DATETIME NULL,
                    `status` INTEGER NOT NULL DEFAULT 1,
                    `created_at` DATETIME NULL
                )",
                "CREATE UNIQUE INDEX IF NOT EXISTS `{$p}coupons_code` ON `{$p}coupons` (`code`)",
                "CREATE TABLE IF NOT EXISTS `{$p}coupon_uses` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `coupon_id` INTEGER NOT NULL,
                    `user_id` INTEGER NOT NULL,
                    `order_id` INTEGER NOT NULL,
                    `created_at` DATETIME NULL
                )",
                "CREATE INDEX IF NOT EXISTS `{$p}coupon_uses_c` ON `{$p}coupon_uses` (`coupon_id`)",
                "CREATE TABLE IF NOT EXISTS `{$p}tickets` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `user_id` INTEGER NOT NULL,
                    `subject` VARCHAR(200) NOT NULL,
                    `category` VARCHAR(32) NOT NULL DEFAULT 'other',
                    `priority` INTEGER NOT NULL DEFAULT 1,
                    `status` INTEGER NOT NULL DEFAULT 0,
                    `last_reply_at` DATETIME NULL,
                    `created_at` DATETIME NULL,
                    `updated_at` DATETIME NULL
                )",
                "CREATE INDEX IF NOT EXISTS `{$p}tickets_user` ON `{$p}tickets` (`user_id`)",
                "CREATE TABLE IF NOT EXISTS `{$p}ticket_replies` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `ticket_id` INTEGER NOT NULL,
                    `user_id` INTEGER NOT NULL,
                    `is_admin` INTEGER NOT NULL DEFAULT 0,
                    `content` TEXT NOT NULL,
                    `created_at` DATETIME NULL
                )",
                "CREATE INDEX IF NOT EXISTS `{$p}ticket_replies_t` ON `{$p}ticket_replies` (`ticket_id`)",
                "CREATE TABLE IF NOT EXISTS `{$p}pages` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `title` VARCHAR(128) NOT NULL,
                    `slug` VARCHAR(64) NOT NULL,
                    `content` TEXT NULL,
                    `status` INTEGER NOT NULL DEFAULT 1,
                    `sortable` INTEGER NOT NULL DEFAULT 0,
                    `show_in_footer` INTEGER NOT NULL DEFAULT 1,
                    `created_at` DATETIME NULL,
                    `updated_at` DATETIME NULL
                )",
                "CREATE UNIQUE INDEX IF NOT EXISTS `{$p}pages_slug` ON `{$p}pages` (`slug`)",
                "CREATE TABLE IF NOT EXISTS `{$p}reports` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `image_id` INTEGER NULL,
                    `album_id` INTEGER NULL,
                    `reporter_id` INTEGER NULL,
                    `category` VARCHAR(32) NOT NULL DEFAULT 'other',
                    `reason` VARCHAR(500) NULL,
                    `status` INTEGER NOT NULL DEFAULT 0,
                    `handler_id` INTEGER NULL,
                    `handle_note` VARCHAR(500) NULL,
                    `created_at` DATETIME NULL,
                    `handled_at` DATETIME NULL
                )",
                "CREATE INDEX IF NOT EXISTS `{$p}reports_status` ON `{$p}reports` (`status`)",
                "CREATE TABLE IF NOT EXISTS `{$p}feedbacks` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `user_id` INTEGER NULL,
                    `kind` VARCHAR(16) NOT NULL DEFAULT 'suggestion',
                    `content` TEXT NOT NULL,
                    `contact` VARCHAR(128) NULL,
                    `status` INTEGER NOT NULL DEFAULT 0,
                    `reply` TEXT NULL,
                    `created_at` DATETIME NULL,
                    `replied_at` DATETIME NULL
                )",
                "CREATE TABLE IF NOT EXISTS `{$p}notices` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `title` VARCHAR(128) NOT NULL,
                    `content` TEXT NULL,
                    `level` VARCHAR(16) NOT NULL DEFAULT 'info',
                    `pinned` INTEGER NOT NULL DEFAULT 0,
                    `status` INTEGER NOT NULL DEFAULT 1,
                    `popup` INTEGER NOT NULL DEFAULT 0,
                    `expire_at` DATETIME NULL,
                    `created_at` DATETIME NULL,
                    `updated_at` DATETIME NULL
                )",
                "CREATE TABLE IF NOT EXISTS `{$p}user_oauth` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `user_id` INTEGER NOT NULL,
                    `driver_id` INTEGER NULL,
                    `provider` VARCHAR(32) NOT NULL,
                    `openid` VARCHAR(128) NOT NULL,
                    `unionid` VARCHAR(128) NULL,
                    `nickname` VARCHAR(128) NULL,
                    `avatar` VARCHAR(255) NULL,
                    `created_at` DATETIME NULL
                )",
                "CREATE UNIQUE INDEX IF NOT EXISTS `{$p}user_oauth_key` ON `{$p}user_oauth` (`provider`,`openid`)",
            ];
        }

        return [
            "CREATE TABLE IF NOT EXISTS `{$p}drivers` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(64) NOT NULL,
                `type` VARCHAR(16) NOT NULL,
                `provider` VARCHAR(32) NOT NULL,
                `config` TEXT NULL,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `sortable` INT NOT NULL DEFAULT 0,
                `remark` VARCHAR(255) NULL DEFAULT NULL,
                `created_at` DATETIME NULL DEFAULT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_{$p}drv_type` (`type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}group_storage` (
                `group_id` INT UNSIGNED NOT NULL,
                `storage_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`group_id`,`storage_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}group_driver` (
                `group_id` INT UNSIGNED NOT NULL,
                `driver_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`group_id`,`driver_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}storage_driver` (
                `storage_id` INT UNSIGNED NOT NULL,
                `driver_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`storage_id`,`driver_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}packages` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(64) NOT NULL,
                `group_id` INT UNSIGNED NOT NULL DEFAULT 1,
                `price_cents` BIGINT NOT NULL DEFAULT 0,
                `duration_days` INT NOT NULL DEFAULT 30,
                `capacity` BIGINT NOT NULL DEFAULT 0,
                `max_file_size` BIGINT NOT NULL DEFAULT 0,
                `daily_limit` INT NOT NULL DEFAULT 0,
                `purchase_limit` INT NOT NULL DEFAULT 0,
                `description` VARCHAR(500) NULL DEFAULT NULL,
                `features` TEXT NULL,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `sortable` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NULL DEFAULT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}orders` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_no` VARCHAR(32) NOT NULL,
                `user_id` INT UNSIGNED NOT NULL,
                `package_id` INT UNSIGNED NOT NULL,
                `package_name` VARCHAR(64) NOT NULL,
                `amount_cents` BIGINT NOT NULL DEFAULT 0,
                `discount_cents` BIGINT NOT NULL DEFAULT 0,
                `total_cents` BIGINT NOT NULL DEFAULT 0,
                `coupon_id` INT UNSIGNED NULL DEFAULT NULL,
                `driver_id` INT UNSIGNED NULL DEFAULT NULL,
                `gateway` VARCHAR(32) NULL DEFAULT NULL,
                `trade_no` VARCHAR(80) NULL DEFAULT NULL,
                `status` TINYINT(1) NOT NULL DEFAULT 0,
                `paid_at` DATETIME NULL DEFAULT NULL,
                `expire_at` DATETIME NULL DEFAULT NULL,
                `created_at` DATETIME NULL DEFAULT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_{$p}order_no` (`order_no`),
                KEY `idx_{$p}order_user` (`user_id`),
                KEY `idx_{$p}order_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}coupons` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `code` VARCHAR(32) NOT NULL,
                `name` VARCHAR(64) NOT NULL,
                `type` VARCHAR(10) NOT NULL DEFAULT 'fixed',
                `value` BIGINT NOT NULL DEFAULT 0,
                `min_amount_cents` BIGINT NOT NULL DEFAULT 0,
                `max_discount_cents` BIGINT NOT NULL DEFAULT 0,
                `quota` INT NOT NULL DEFAULT 0,
                `used` INT NOT NULL DEFAULT 0,
                `per_user` INT NOT NULL DEFAULT 1,
                `scope` TEXT NULL,
                `starts_at` DATETIME NULL DEFAULT NULL,
                `expires_at` DATETIME NULL DEFAULT NULL,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_{$p}coupon_code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}coupon_uses` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `coupon_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NOT NULL,
                `order_id` INT UNSIGNED NOT NULL,
                `created_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_{$p}cu_coupon` (`coupon_id`),
                KEY `idx_{$p}cu_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}tickets` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `subject` VARCHAR(200) NOT NULL,
                `category` VARCHAR(32) NOT NULL DEFAULT 'other',
                `priority` TINYINT NOT NULL DEFAULT 1,
                `status` TINYINT NOT NULL DEFAULT 0,
                `last_reply_at` DATETIME NULL DEFAULT NULL,
                `created_at` DATETIME NULL DEFAULT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_{$p}ticket_user` (`user_id`),
                KEY `idx_{$p}ticket_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}ticket_replies` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `ticket_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NOT NULL,
                `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
                `content` TEXT NOT NULL,
                `created_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_{$p}tr_ticket` (`ticket_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}pages` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(128) NOT NULL,
                `slug` VARCHAR(64) NOT NULL,
                `content` MEDIUMTEXT NULL,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `sortable` INT NOT NULL DEFAULT 0,
                `show_in_footer` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL DEFAULT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_{$p}page_slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}reports` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `image_id` INT UNSIGNED NULL DEFAULT NULL,
                `album_id` INT UNSIGNED NULL DEFAULT NULL,
                `reporter_id` INT UNSIGNED NULL DEFAULT NULL,
                `category` VARCHAR(32) NOT NULL DEFAULT 'other',
                `reason` VARCHAR(500) NULL DEFAULT NULL,
                `status` TINYINT NOT NULL DEFAULT 0,
                `handler_id` INT UNSIGNED NULL DEFAULT NULL,
                `handle_note` VARCHAR(500) NULL DEFAULT NULL,
                `created_at` DATETIME NULL DEFAULT NULL,
                `handled_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_{$p}report_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}feedbacks` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NULL DEFAULT NULL,
                `kind` VARCHAR(16) NOT NULL DEFAULT 'suggestion',
                `content` TEXT NOT NULL,
                `contact` VARCHAR(128) NULL DEFAULT NULL,
                `status` TINYINT NOT NULL DEFAULT 0,
                `reply` TEXT NULL,
                `created_at` DATETIME NULL DEFAULT NULL,
                `replied_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}notices` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(128) NOT NULL,
                `content` TEXT NULL,
                `level` VARCHAR(16) NOT NULL DEFAULT 'info',
                `pinned` TINYINT(1) NOT NULL DEFAULT 0,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `popup` TINYINT(1) NOT NULL DEFAULT 0,
                `expire_at` DATETIME NULL DEFAULT NULL,
                `created_at` DATETIME NULL DEFAULT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}user_oauth` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `driver_id` INT UNSIGNED NULL DEFAULT NULL,
                `provider` VARCHAR(32) NOT NULL,
                `openid` VARCHAR(128) NOT NULL,
                `unionid` VARCHAR(128) NULL DEFAULT NULL,
                `nickname` VARCHAR(128) NULL DEFAULT NULL,
                `avatar` VARCHAR(255) NULL DEFAULT NULL,
                `created_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_{$p}oauth_key` (`provider`,`openid`),
                KEY `idx_{$p}oauth_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }

    public static function seeds(string $driver, string $prefix = ''): array
    {
        if (SchemaDef::supports($driver)) {
            return SchemaDef::seeds($driver, $prefix);
        }

        $p = $prefix;
        $now = date('Y-m-d H:i:s');
        $drivers = [
            ['阿里云内容安全', 'security', 'aliyun_green', 0, 10, '图片涉黄涉政自动识别'],
            ['本地敏感词审核', 'security', 'local_audit', 0, 20, '依据文件名与命中词做拦截'],
            ['支付宝', 'payment', 'alipay', 0, 10, '电脑网站支付 / 当面付'],
            ['微信支付', 'payment', 'wechat_pay', 0, 20, 'Native 扫码支付'],
            ['SMTP 邮件', 'mail', 'smtp', 0, 10, '注册验证、工单通知'],
            ['阿里云短信', 'sms', 'aliyun_sms', 0, 10, '验证码下发'],
        ];
        $rows = [];
        foreach ($drivers as $d) {
            $rows[] = [
                'sql' => 'INSERT INTO `' . $p . 'drivers` (`name`,`type`,`provider`,`config`,`status`,`sortable`,`remark`,`created_at`,`updated_at`) VALUES (?,?,?,?,?,?,?,?,?)',
                'args' => [$d[0], $d[1], $d[2], '{}', $d[3], $d[4], $d[5], $now, $now],
            ];
        }

        $rows[] = [
            'sql' => 'INSERT INTO `' . $p . 'packages` (`name`,`group_id`,`price_cents`,`duration_days`,`capacity`,`max_file_size`,`daily_limit`,`description`,`features`,`status`,`sortable`,`created_at`,`updated_at`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            'args' => ['免费体验（30 天）', 1, 0, 30, 1073741824, 5242880, 20, '注册即得，适合轻量使用', json_encode(['1 GB 存储', '单文件 5 MB', '每日 20 张', '图片广场展示'], JSON_UNESCAPED_UNICODE), 1, 10, $now, $now],
        ];
        $rows[] = [
            'sql' => 'INSERT INTO `' . $p . 'packages` (`name`,`group_id`,`price_cents`,`duration_days`,`capacity`,`max_file_size`,`daily_limit`,`description`,`features`,`status`,`sortable`,`created_at`,`updated_at`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            'args' => ['专业版（年付）', 1, 9900, 365, 53687091200, 20971520, 0, '不限每日张数，单文件 20 MB', json_encode(['50 GB 存储', '单文件 20 MB', '不限每日张数', '打包下载', '工单优先响应'], JSON_UNESCAPED_UNICODE), 1, 20, $now, $now],
        ];

        $rows[] = [
            'sql' => 'INSERT INTO `' . $p . 'notices` (`title`,`content`,`level`,`pinned`,`status`,`expire_at`,`created_at`,`updated_at`) VALUES (?,?,?,?,?,?,?,?)',
            'args' => ['欢迎使用蜗牛图床', '上传遇到问题可以在工单里提交，工作日 24 小时内回复。', 'info', 1, 1, null, $now, $now],
        ];
        $rows[] = [
            'sql' => 'INSERT INTO `' . $p . 'pages` (`title`,`slug`,`content`,`status`,`sortable`,`show_in_footer`,`created_at`,`updated_at`) VALUES (?,?,?,?,?,?,?,?)',
            'args' => ['关于我们', 'about', '<p>蜗牛图床提供稳定、快速、可扩展的图片托管服务。</p>', 1, 10, 1, $now, $now],
        ];
        $rows[] = [
            'sql' => 'INSERT INTO `' . $p . 'pages` (`title`,`slug`,`content`,`status`,`sortable`,`show_in_footer`,`created_at`,`updated_at`) VALUES (?,?,?,?,?,?,?,?)',
            'args' => ['服务条款', 'terms', '<p>上传内容需遵守所在地法律法规，禁止上传违法与侵权内容。</p>', 1, 20, 1, $now, $now],
        ];
        $rows[] = [
            'sql' => 'INSERT INTO `' . $p . 'pages` (`title`,`slug`,`content`,`status`,`sortable`,`show_in_footer`,`created_at`,`updated_at`) VALUES (?,?,?,?,?,?,?,?)',
            'args' => ['隐私政策', 'privacy', '<p>我们仅收集运行服务所必需的信息，不会向第三方出售用户数据。</p>', 1, 30, 1, $now, $now],
        ];

        return $rows;
    }
}
