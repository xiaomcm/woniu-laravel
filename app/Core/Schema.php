<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Core;

use PDO;

class Schema
{
    public static function install(PDO $pdo, string $driver, string $prefix = ''): void
    {
        foreach (self::statements($driver, $prefix) as $sql) {
            $pdo->exec($sql);
        }

        foreach (SchemaExtra::tables($driver, $prefix) as $sql) {
            $pdo->exec($sql);
        }

        if ((int) $pdo->query('SELECT COUNT(*) FROM `' . $prefix . 'groups`')->fetchColumn() === 0) {
            $stmt = $pdo->prepare(
                'INSERT INTO `' . $prefix . 'groups`
                 (`name`,`is_default`,`capacity`,`max_file_size`,`allowed_extensions`,`default_storage_id`,`max_upload_num`,`watermark`,`created_at`)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                '默认组', 1, 0, 5242880, 'jpg,jpeg,png,gif,bmp,webp,svg,ico', 1, 10, 0, date('Y-m-d H:i:s'),
            ]);
        }



        if ((int) $pdo->query('SELECT COUNT(*) FROM `' . $prefix . 'storages`')->fetchColumn() === 0) {
            $stmt = $pdo->prepare(
                'INSERT INTO `' . $prefix . 'storages` (`name`,`type`,`config`,`url`,`is_default`,`status`,`created_at`,`updated_at`)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                '本地存储',
                'local',
                json_encode(['root' => 'storage/uploads'], JSON_UNESCAPED_SLASHES),
                '',
                1,
                1,
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s'),
            ]);
        }



        $existing = $pdo->query('SELECT COUNT(*) FROM `' . $prefix . 'settings`')->fetchColumn();
        if ((int) $existing === 0) {
            $stmt = $pdo->prepare('INSERT INTO `' . $prefix . 'settings` (`key`,`value`) VALUES (?,?)');
            foreach (Setting::defaults() as $key => $value) {
                $stmt->execute([$key, (string) $value]);
            }
        }

        foreach (['group_storage', 'group_driver', 'storage_driver'] as $pivot) {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM `' . $prefix . $pivot . '`')->fetchColumn();
            if ($count > 0) {
                continue;
            }
            if ($pivot === 'group_storage') {
                $pdo->exec('INSERT INTO `' . $prefix . 'group_storage` (`group_id`,`storage_id`) VALUES (1,1)');
            }
        }

        if ((int) $pdo->query('SELECT COUNT(*) FROM `' . $prefix . 'drivers`')->fetchColumn() === 0) {
            foreach (SchemaExtra::seeds($driver, $prefix) as $seed) {
                try {
                    $pdo->prepare($seed['sql'])->execute($seed['args']);
                } catch (\Throwable $e) {
                }
            }
        }
    }

    public static function createAdmin(PDO $pdo, string $prefix, string $name, string $email, string $password): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO `' . $prefix . 'users`
             (`name`,`email`,`password`,`group_id`,`capacity`,`is_admin`,`status`,`created_at`,`updated_at`)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $name,
            strtolower($email),
            password_hash($password, PASSWORD_DEFAULT),
            1,
            null,
            1,
            1,
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s'),
        ]);
        return (int) $pdo->lastInsertId();
    }

    private static function statements(string $driver, string $prefix): array
    {
        // PostgreSQL / SQL Server：定义式生成器统一产出
        if (SchemaDef::supports($driver)) {
            return SchemaDef::coreStatements($driver, $prefix);
        }

        $p = $prefix;

        if ($driver === 'sqlite') {
            return [
                "CREATE TABLE IF NOT EXISTS `{$p}users` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `name` VARCHAR(64) NOT NULL,
                    `email` VARCHAR(128) NOT NULL,
                    `password` VARCHAR(255) NOT NULL,
                    `group_id` INTEGER NOT NULL DEFAULT 1,
                    `capacity` BIGINT NULL,
                    `group_expired_at` DATETIME NULL,
                    `is_admin` INTEGER NOT NULL DEFAULT 0,
                    `status` INTEGER NOT NULL DEFAULT 1,
                    `avatar` VARCHAR(255) NULL,
                    `phone` VARCHAR(32) NULL,
                    `created_at` DATETIME NOT NULL,
                    `updated_at` DATETIME NULL,
                    `last_login_at` DATETIME NULL
                )",
                "CREATE UNIQUE INDEX IF NOT EXISTS `{$p}users_email` ON `{$p}users` (`email`)",
                "CREATE UNIQUE INDEX IF NOT EXISTS `{$p}users_name` ON `{$p}users` (`name`)",
                "CREATE TABLE IF NOT EXISTS `{$p}groups` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `name` VARCHAR(64) NOT NULL,
                    `is_default` INTEGER NOT NULL DEFAULT 0,
                    `capacity` BIGINT NOT NULL DEFAULT 0,
                    `max_file_size` BIGINT NOT NULL DEFAULT 0,
                    `allowed_extensions` TEXT NULL,
                    `default_storage_id` INTEGER NULL,
                    `max_upload_num` INTEGER NOT NULL DEFAULT 10,
                    `daily_limit` INTEGER NOT NULL DEFAULT 0,
                    `permissions` TEXT NULL,
                    `watermark` INTEGER NOT NULL DEFAULT 0,
                    `created_at` DATETIME NULL,
                    `updated_at` DATETIME NULL
                )",
                "CREATE TABLE IF NOT EXISTS `{$p}storages` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `name` VARCHAR(64) NOT NULL,
                    `type` VARCHAR(32) NOT NULL,
                    `config` TEXT NULL,
                    `url` VARCHAR(255) NULL,
                    `is_default` INTEGER NOT NULL DEFAULT 0,
                    `status` INTEGER NOT NULL DEFAULT 1,
                    `created_at` DATETIME NULL,
                    `updated_at` DATETIME NULL
                )",
                "CREATE TABLE IF NOT EXISTS `{$p}albums` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `user_id` INTEGER NOT NULL,
                    `name` VARCHAR(128) NOT NULL,
                    `intro` VARCHAR(255) NULL,
                    `cover` VARCHAR(255) NULL,
                    `image_num` INTEGER NOT NULL DEFAULT 0,
                    `is_public` INTEGER NOT NULL DEFAULT 0,
                    `key` VARCHAR(32) NULL,
                    `created_at` DATETIME NULL,
                    `updated_at` DATETIME NULL
                )",
                "CREATE UNIQUE INDEX IF NOT EXISTS `{$p}albums_key` ON `{$p}albums` (`key`)",
                "CREATE TABLE IF NOT EXISTS `{$p}images` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `user_id` INTEGER NOT NULL DEFAULT 0,
                    `album_id` INTEGER NULL,
                    `storage_id` INTEGER NOT NULL DEFAULT 1,
                    `name` VARCHAR(255) NOT NULL,
                    `original_name` VARCHAR(255) NOT NULL,
                    `description` VARCHAR(500) NULL,
                    `tags` VARCHAR(255) NULL,
                    `pathname` VARCHAR(500) NOT NULL,
                    `mimetype` VARCHAR(64) NOT NULL,
                    `extension` VARCHAR(16) NOT NULL,
                    `size` BIGINT NOT NULL DEFAULT 0,
                    `md5` VARCHAR(32) NULL,
                    `sha1` VARCHAR(40) NULL,
                    `width` INTEGER NOT NULL DEFAULT 0,
                    `height` INTEGER NOT NULL DEFAULT 0,
                    `url` VARCHAR(500) NOT NULL,
                    `thumb_url` VARCHAR(500) NULL,
                    `views` INTEGER NOT NULL DEFAULT 0,
                    `status` INTEGER NOT NULL DEFAULT 1,
                    `is_public` INTEGER NOT NULL DEFAULT 1,
                    `deleted_at` DATETIME NULL,
                    `created_at` DATETIME NOT NULL,
                    `updated_at` DATETIME NULL
                )",
                "CREATE INDEX IF NOT EXISTS `{$p}images_user` ON `{$p}images` (`user_id`)",
                "CREATE INDEX IF NOT EXISTS `{$p}images_album` ON `{$p}images` (`album_id`)",
                "CREATE INDEX IF NOT EXISTS `{$p}images_sha1` ON `{$p}images` (`sha1`)",
                "CREATE INDEX IF NOT EXISTS `{$p}images_deleted` ON `{$p}images` (`deleted_at`)",
                "CREATE TABLE IF NOT EXISTS `{$p}tokens` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `user_id` INTEGER NOT NULL,
                    `name` VARCHAR(64) NOT NULL,
                    `token_hash` VARCHAR(64) NOT NULL,
                    `token_prefix` VARCHAR(16) NOT NULL,
                    `last_used_at` DATETIME NULL,
                    `expires_at` DATETIME NULL,
                    `created_at` DATETIME NULL
                )",
                "CREATE UNIQUE INDEX IF NOT EXISTS `{$p}tokens_hash` ON `{$p}tokens` (`token_hash`)",
                "CREATE TABLE IF NOT EXISTS `{$p}settings` (
                    `key` VARCHAR(64) PRIMARY KEY,
                    `value` TEXT NULL
                )",
                "CREATE TABLE IF NOT EXISTS `{$p}logs` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `user_id` INTEGER NULL,
                    `action` VARCHAR(64) NOT NULL,
                    `ip` VARCHAR(45) NULL,
                    `ua` VARCHAR(255) NULL,
                    `detail` TEXT NULL,
                    `created_at` DATETIME NULL
                )",
            ];
        }

        return [
            "CREATE TABLE IF NOT EXISTS `{$p}users` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(64) NOT NULL,
                `email` VARCHAR(128) NOT NULL,
                `password` VARCHAR(255) NOT NULL,
                `group_id` INT UNSIGNED NOT NULL DEFAULT 1,
                `capacity` BIGINT NULL DEFAULT NULL,
                `group_expired_at` DATETIME NULL DEFAULT NULL,
                `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `avatar` VARCHAR(255) NULL DEFAULT NULL,
                `phone` VARCHAR(32) NULL DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL,
                `last_login_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_{$p}email` (`email`),
                UNIQUE KEY `uk_{$p}name` (`name`),
                KEY `idx_{$p}group` (`group_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}groups` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(64) NOT NULL,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `capacity` BIGINT NOT NULL DEFAULT 0,
                `max_file_size` BIGINT NOT NULL DEFAULT 0,
                `allowed_extensions` TEXT NULL,
                `default_storage_id` INT UNSIGNED NULL DEFAULT NULL,
                `max_upload_num` INT NOT NULL DEFAULT 10,
                `daily_limit` INT NOT NULL DEFAULT 0,
                `permissions` TEXT NULL,
                `watermark` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NULL DEFAULT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}storages` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(64) NOT NULL,
                `type` VARCHAR(32) NOT NULL,
                `config` TEXT NULL,
                `url` VARCHAR(255) NULL DEFAULT NULL,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL DEFAULT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}albums` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(128) NOT NULL,
                `intro` VARCHAR(255) NULL DEFAULT NULL,
                `cover` VARCHAR(255) NULL DEFAULT NULL,
                `image_num` INT NOT NULL DEFAULT 0,
                `is_public` TINYINT(1) NOT NULL DEFAULT 0,
                `key` VARCHAR(32) NULL DEFAULT NULL,
                `created_at` DATETIME NULL DEFAULT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_{$p}album_key` (`key`),
                KEY `idx_{$p}album_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}images` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `album_id` INT UNSIGNED NULL DEFAULT NULL,
                `storage_id` INT UNSIGNED NOT NULL DEFAULT 1,
                `name` VARCHAR(255) NOT NULL,
                `original_name` VARCHAR(255) NOT NULL,
                `description` VARCHAR(500) NULL DEFAULT NULL,
                `tags` VARCHAR(255) NULL DEFAULT NULL,
                `pathname` VARCHAR(500) NOT NULL,
                `mimetype` VARCHAR(64) NOT NULL,
                `extension` VARCHAR(16) NOT NULL,
                `size` BIGINT NOT NULL DEFAULT 0,
                `md5` VARCHAR(32) NULL DEFAULT NULL,
                `sha1` VARCHAR(40) NULL DEFAULT NULL,
                `width` INT NOT NULL DEFAULT 0,
                `height` INT NOT NULL DEFAULT 0,
                `url` VARCHAR(500) NOT NULL,
                `thumb_url` VARCHAR(500) NULL DEFAULT NULL,
                `views` INT UNSIGNED NOT NULL DEFAULT 0,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `is_public` TINYINT(1) NOT NULL DEFAULT 1,
                `deleted_at` DATETIME NULL DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_{$p}img_user` (`user_id`),
                KEY `idx_{$p}img_album` (`album_id`),
                KEY `idx_{$p}img_sha1` (`sha1`),
                KEY `idx_{$p}img_deleted` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}tokens` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(64) NOT NULL,
                `token_hash` VARCHAR(64) NOT NULL,
                `token_prefix` VARCHAR(16) NOT NULL,
                `last_used_at` DATETIME NULL DEFAULT NULL,
                `expires_at` DATETIME NULL DEFAULT NULL,
                `created_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_{$p}token_hash` (`token_hash`),
                KEY `idx_{$p}token_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}settings` (
                `key` VARCHAR(64) NOT NULL,
                `value` TEXT NULL,
                PRIMARY KEY (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$p}logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NULL DEFAULT NULL,
                `action` VARCHAR(64) NOT NULL,
                `ip` VARCHAR(45) NULL DEFAULT NULL,
                `ua` VARCHAR(255) NULL DEFAULT NULL,
                `detail` TEXT NULL,
                `created_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_{$p}log_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }
}
