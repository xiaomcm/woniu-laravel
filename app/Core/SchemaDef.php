<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 *
 * 跨数据库 DDL 定义式生成器。
 *
 * 一份表定义（字段类型/约束/索引）同时支持 PostgreSQL 与 SQL Server。
 * MySQL / SQLite 仍由 App\Core\Schema 与 App\Core\SchemaExtra 中的 raw SQL 分支处理，
 * 这里只负责 pgsql / sqlsrv 两套方言，避免破坏现有回归测试。
 */

declare(strict_types=1);

namespace App\Core;

class SchemaDef
{
    /** 仅这两类数据库走本生成器 */
    public const SUPPORTED = ['pgsql', 'sqlsrv'];

    private const CORE_TABLES = [
        'users', 'groups', 'storages', 'albums', 'images', 'tokens', 'settings', 'logs',
    ];

    private const EXTRA_TABLES = [
        'drivers', 'group_storage', 'group_driver', 'storage_driver',
        'packages', 'orders', 'coupons', 'coupon_uses',
        'tickets', 'ticket_replies', 'pages', 'reports', 'feedbacks', 'notices', 'user_oauth',
    ];

    /**
     * 字段类型映射（不含长度/修饰）。
     * id 类型在 columnType() 里单独处理自增与主键。
     */
    private static array $types = [
        'pgsql' => [
            'id'         => 'BIGSERIAL',
            'uint'       => 'BIGINT',
            'int'        => 'INTEGER',
            'tinyint'    => 'SMALLINT',
            'bool'       => 'BOOLEAN',
            'string'     => 'VARCHAR',
            'text'       => 'TEXT',
            'mediumtext' => 'TEXT',
            'bigint'     => 'BIGINT',
            'datetime'   => 'TIMESTAMP',
        ],
        'sqlsrv' => [
            'id'         => 'BIGINT IDENTITY(1,1)',
            'uint'       => 'BIGINT',
            'int'        => 'INT',
            'tinyint'    => 'TINYINT',
            'bool'       => 'BIT',
            'string'     => 'NVARCHAR',
            'text'       => 'VARCHAR(MAX)',
            'mediumtext' => 'VARCHAR(MAX)',
            'bigint'     => 'BIGINT',
            'datetime'   => 'DATETIME',
        ],
    ];

    public static function supports(string $driver): bool
    {
        return in_array($driver, self::SUPPORTED, true);
    }

    public static function coreStatements(string $driver, string $prefix): array
    {
        return self::render($driver, $prefix, self::CORE_TABLES);
    }

    public static function extraTables(string $driver, string $prefix): array
    {
        return self::render($driver, $prefix, self::EXTRA_TABLES);
    }

    public static function seeds(string $driver, string $prefix): array
    {
        $now = date('Y-m-d H:i:s');
        $q = static fn (string $n) => self::quote($driver, $n);
        $s = static fn (string $v) => self::literal($driver, $v);
        $rows = [];

        // drivers
        $drivers = [
            ['阿里云内容安全', 'security', 'aliyun_green', 0, 10, '图片涉黄涉政自动识别'],
            ['本地敏感词审核', 'security', 'local_audit', 0, 20, '依据文件名与命中词做拦截'],
            ['支付宝', 'payment', 'alipay', 0, 10, '电脑网站支付 / 当面付'],
            ['微信支付', 'payment', 'wechat_pay', 0, 20, 'Native 扫码支付'],
            ['SMTP 邮件', 'mail', 'smtp', 0, 10, '注册验证、工单通知'],
            ['阿里云短信', 'sms', 'aliyun_sms', 0, 10, '验证码下发'],
        ];
        foreach ($drivers as $d) {
            $rows[] = 'INSERT INTO ' . $q($prefix . 'drivers') . ' ('
                . $q('name') . ',' . $q('type') . ',' . $q('provider') . ',' . $q('config') . ','
                . $q('status') . ',' . $q('sortable') . ',' . $q('remark') . ',' . $q('created_at') . ',' . $q('updated_at')
                . ') VALUES (' . $s($d[0]) . ',' . $s($d[1]) . ',' . $s($d[2]) . ',' . $s('{}') . ','
                . (int) $d[3] . ',' . (int) $d[4] . ',' . $s($d[5]) . ',' . $s($now) . ',' . $s($now) . ')';
        }

        // packages
        $freeFeatures = json_encode(['1 GB 存储', '单文件 5 MB', '每日 20 张', '图片广场展示'], JSON_UNESCAPED_UNICODE);
        $proFeatures = json_encode(['50 GB 存储', '单文件 20 MB', '不限每日张数', '打包下载', '工单优先响应'], JSON_UNESCAPED_UNICODE);
        $pkgCols = $q('name') . ',' . $q('group_id') . ',' . $q('price_cents') . ',' . $q('duration_days')
            . ',' . $q('capacity') . ',' . $q('max_file_size') . ',' . $q('daily_limit') . ',' . $q('description')
            . ',' . $q('features') . ',' . $q('status') . ',' . $q('sortable') . ',' . $q('created_at') . ',' . $q('updated_at');
        $rows[] = 'INSERT INTO ' . $q($prefix . 'packages') . ' (' . $pkgCols . ') VALUES ('
            . $s('免费体验（30 天）') . ',1,0,30,1073741824,5242880,20,' . $s('注册即得，适合轻量使用') . ',' . $s($freeFeatures) . ',1,10,' . $s($now) . ',' . $s($now) . ')';
        $rows[] = 'INSERT INTO ' . $q($prefix . 'packages') . ' (' . $pkgCols . ') VALUES ('
            . $s('专业版（年付）') . ',1,9900,365,53687091200,20971520,0,' . $s('不限每日张数，单文件 20 MB') . ',' . $s($proFeatures) . ',1,20,' . $s($now) . ',' . $s($now) . ')';

        // notices
        $rows[] = 'INSERT INTO ' . $q($prefix . 'notices') . ' ('
            . $q('title') . ',' . $q('content') . ',' . $q('level') . ',' . $q('pinned') . ',' . $q('status') . ',' . $q('expire_at') . ',' . $q('created_at') . ',' . $q('updated_at')
            . ') VALUES (' . $s('欢迎使用蜗牛图床') . ',' . $s('上传遇到问题可以在工单里提交，工作日 24 小时内回复。') . ',' . $s('info') . ',1,1,NULL,' . $s($now) . ',' . $s($now) . ')';

        // pages
        $pageCols = $q('title') . ',' . $q('slug') . ',' . $q('content') . ',' . $q('status') . ',' . $q('sortable') . ',' . $q('show_in_footer') . ',' . $q('created_at') . ',' . $q('updated_at');
        $rows[] = 'INSERT INTO ' . $q($prefix . 'pages') . ' (' . $pageCols . ') VALUES ('
            . $s('关于我们') . ',' . $s('about') . ',' . $s('<p>蜗牛图床提供稳定、快速、可扩展的图片托管服务。</p>') . ',1,10,1,' . $s($now) . ',' . $s($now) . ')';
        $rows[] = 'INSERT INTO ' . $q($prefix . 'pages') . ' (' . $pageCols . ') VALUES ('
            . $s('服务条款') . ',' . $s('terms') . ',' . $s('<p>上传内容需遵守所在地法律法规，禁止上传违法与侵权内容。</p>') . ',1,20,1,' . $s($now) . ',' . $s($now) . ')';
        $rows[] = 'INSERT INTO ' . $q($prefix . 'pages') . ' (' . $pageCols . ') VALUES ('
            . $s('隐私政策') . ',' . $s('privacy') . ',' . $s('<p>我们仅收集运行服务所必需的信息，不会向第三方出售用户数据。</p>') . ',1,30,1,' . $s($now) . ',' . $s($now) . ')';

        return $rows;
    }

    private static function render(string $driver, string $prefix, array $names): array
    {
        $sql = [];
        foreach ($names as $name) {
            $def = self::tables()[$name];
            $table = $prefix . $name;
            $sql[] = self::createTable($driver, $table, $def);
            foreach ($def['unique'] ?? [] as $cols) {
                $sql[] = self::createIndex($driver, $table, $cols, true);
            }
            foreach ($def['index'] ?? [] as $cols) {
                $sql[] = self::createIndex($driver, $table, $cols, false);
            }
        }
        return $sql;
    }

    private static function createTable(string $driver, string $table, array $def): string
    {
        $q = static fn (string $n) => self::quote($driver, $n);
        $lines = [];
        $hasId = false;
        foreach ($def['cols'] as $cname => $col) {
            if (($col['t'] ?? '') === 'id') {
                $hasId = true;
            }
            $lines[] = '  ' . $q($cname) . ' ' . self::columnType($driver, $col);
        }

        if (isset($def['pk'])) {
            if ($driver === 'pgsql' || !$hasId) {
                $pkCols = implode(', ', array_map($q, array_merge(...$def['pk'])));
                $lines[] = '  PRIMARY KEY (' . $pkCols . ')';
            }
        } elseif ($hasId && $driver === 'pgsql') {
            $lines[] = '  PRIMARY KEY (' . $q('id') . ')';
        }

        $body = implode(",\n", $lines);

        if ($driver === 'pgsql') {
            return 'CREATE TABLE IF NOT EXISTS ' . $q($table) . " (\n" . $body . "\n)";
        }

        // SQL Server 不支持 CREATE TABLE IF NOT EXISTS
        return "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = '{$table}')\nBEGIN\nCREATE TABLE " . $q($table) . " (\n" . $body . "\n)\nEND";
    }

    private static function columnType(string $driver, array $col): string
    {
        $t = $col['t'] ?? 'text';
        if ($t === 'id') {
            // pgsql 走表级 PK；sqlsrv 在列级直接 PK（IDENTITY 在类型里）
            return $driver === 'pgsql' ? 'BIGSERIAL' : 'BIGINT IDENTITY(1,1) PRIMARY KEY';
        }
        $base = self::$types[$driver][$t] ?? 'TEXT';
        if ($t === 'string') {
            $base .= '(' . (int) ($col['len'] ?? 255) . ')';
        }
        $parts = [$base];
        $parts[] = ($col['null'] ?? false) ? 'NULL' : 'NOT NULL';
        if (array_key_exists('default', $col)) {
            $parts[] = 'DEFAULT ' . $col['default'];
        }
        return implode(' ', $parts);
    }

    private static function createIndex(string $driver, string $table, array $cols, bool $unique): string
    {
        $q = static fn (string $n) => self::quote($driver, $n);
        $idxName = substr($table . '_' . implode('_', $cols), 0, 120);
        $colsSql = implode(', ', array_map($q, $cols));
        $kw = $unique ? 'UNIQUE INDEX' : 'INDEX';

        if ($driver === 'pgsql') {
            return 'CREATE ' . $kw . ' IF NOT EXISTS ' . $q($idxName) . ' ON ' . $q($table) . ' (' . $colsSql . ')';
        }
        return "IF NOT EXISTS (SELECT * FROM sys.indexes WHERE name = '{$idxName}')\nCREATE " . $kw . ' ' . $q($idxName) . ' ON ' . $q($table) . ' (' . $colsSql . ')';
    }

    private static function quote(string $driver, string $name): string
    {
        return $driver === 'pgsql' ? '"' . $name . '"' : '[' . $name . ']';
    }

    private static function literal(string $driver, string $v): string
    {
        $escaped = str_replace("'", "''", $v);
        return $driver === 'pgsql' ? "'" . $escaped . "'" : "N'" . $escaped . "'";
    }

    /**
     * 23 张表的权威字段定义。
     * 列定义：t=类型键, len=长度(string 用), null=可空(默认 false=NOT NULL), default=原生 SQL 片段。
     */
    private static function tables(): array
    {
        return [
            'users' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'name' => ['t' => 'string', 'len' => 64],
                    'email' => ['t' => 'string', 'len' => 128],
                    'password' => ['t' => 'string', 'len' => 255],
                    'group_id' => ['t' => 'uint', 'default' => '1'],
                    'capacity' => ['t' => 'bigint', 'null' => true],
                    'group_expired_at' => ['t' => 'datetime', 'null' => true],
                    'is_admin' => ['t' => 'bool', 'default' => '0'],
                    'status' => ['t' => 'bool', 'default' => '1'],
                    'avatar' => ['t' => 'string', 'len' => 255, 'null' => true],
                    'phone' => ['t' => 'string', 'len' => 32, 'null' => true],
                    'created_at' => ['t' => 'datetime'],
                    'updated_at' => ['t' => 'datetime', 'null' => true],
                    'last_login_at' => ['t' => 'datetime', 'null' => true],
                ],
                'unique' => [['email'], ['name']],
                'index' => [['group_id']],
            ],
            'groups' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'name' => ['t' => 'string', 'len' => 64],
                    'is_default' => ['t' => 'bool', 'default' => '0'],
                    'capacity' => ['t' => 'bigint', 'default' => '0'],
                    'max_file_size' => ['t' => 'bigint', 'default' => '0'],
                    'allowed_extensions' => ['t' => 'text', 'null' => true],
                    'default_storage_id' => ['t' => 'uint', 'null' => true],
                    'max_upload_num' => ['t' => 'int', 'default' => '10'],
                    'daily_limit' => ['t' => 'int', 'default' => '0'],
                    'permissions' => ['t' => 'text', 'null' => true],
                    'watermark' => ['t' => 'bool', 'default' => '0'],
                    'created_at' => ['t' => 'datetime', 'null' => true],
                    'updated_at' => ['t' => 'datetime', 'null' => true],
                ],
            ],
            'storages' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'name' => ['t' => 'string', 'len' => 64],
                    'type' => ['t' => 'string', 'len' => 32],
                    'config' => ['t' => 'text', 'null' => true],
                    'url' => ['t' => 'string', 'len' => 255, 'null' => true],
                    'is_default' => ['t' => 'bool', 'default' => '0'],
                    'status' => ['t' => 'bool', 'default' => '1'],
                    'created_at' => ['t' => 'datetime', 'null' => true],
                    'updated_at' => ['t' => 'datetime', 'null' => true],
                ],
            ],
            'albums' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'user_id' => ['t' => 'uint'],
                    'name' => ['t' => 'string', 'len' => 128],
                    'intro' => ['t' => 'string', 'len' => 255, 'null' => true],
                    'cover' => ['t' => 'string', 'len' => 255, 'null' => true],
                    'image_num' => ['t' => 'int', 'default' => '0'],
                    'is_public' => ['t' => 'bool', 'default' => '0'],
                    'key' => ['t' => 'string', 'len' => 32, 'null' => true],
                    'created_at' => ['t' => 'datetime', 'null' => true],
                    'updated_at' => ['t' => 'datetime', 'null' => true],
                ],
                'unique' => [['key']],
                'index' => [['user_id']],
            ],
            'images' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'user_id' => ['t' => 'uint', 'default' => '0'],
                    'album_id' => ['t' => 'uint', 'null' => true],
                    'storage_id' => ['t' => 'uint', 'default' => '1'],
                    'name' => ['t' => 'string', 'len' => 255],
                    'original_name' => ['t' => 'string', 'len' => 255],
                    'description' => ['t' => 'string', 'len' => 500, 'null' => true],
                    'tags' => ['t' => 'string', 'len' => 255, 'null' => true],
                    'pathname' => ['t' => 'string', 'len' => 500],
                    'mimetype' => ['t' => 'string', 'len' => 64],
                    'extension' => ['t' => 'string', 'len' => 16],
                    'size' => ['t' => 'bigint', 'default' => '0'],
                    'md5' => ['t' => 'string', 'len' => 32, 'null' => true],
                    'sha1' => ['t' => 'string', 'len' => 40, 'null' => true],
                    'width' => ['t' => 'int', 'default' => '0'],
                    'height' => ['t' => 'int', 'default' => '0'],
                    'url' => ['t' => 'string', 'len' => 500],
                    'thumb_url' => ['t' => 'string', 'len' => 500, 'null' => true],
                    'views' => ['t' => 'uint', 'default' => '0'],
                    'status' => ['t' => 'bool', 'default' => '1'],
                    'is_public' => ['t' => 'bool', 'default' => '1'],
                    'deleted_at' => ['t' => 'datetime', 'null' => true],
                    'created_at' => ['t' => 'datetime'],
                    'updated_at' => ['t' => 'datetime', 'null' => true],
                ],
                'index' => [['user_id'], ['album_id'], ['sha1'], ['deleted_at']],
            ],
            'tokens' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'user_id' => ['t' => 'uint'],
                    'name' => ['t' => 'string', 'len' => 64],
                    'token_hash' => ['t' => 'string', 'len' => 64],
                    'token_prefix' => ['t' => 'string', 'len' => 16],
                    'last_used_at' => ['t' => 'datetime', 'null' => true],
                    'expires_at' => ['t' => 'datetime', 'null' => true],
                    'created_at' => ['t' => 'datetime', 'null' => true],
                ],
                'unique' => [['token_hash']],
                'index' => [['user_id']],
            ],
            'settings' => [
                'cols' => [
                    'key' => ['t' => 'string', 'len' => 64],
                    'value' => ['t' => 'text', 'null' => true],
                ],
                'pk' => [['key']],
            ],
            'logs' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'user_id' => ['t' => 'uint', 'null' => true],
                    'action' => ['t' => 'string', 'len' => 64],
                    'ip' => ['t' => 'string', 'len' => 45, 'null' => true],
                    'ua' => ['t' => 'string', 'len' => 255, 'null' => true],
                    'detail' => ['t' => 'text', 'null' => true],
                    'created_at' => ['t' => 'datetime', 'null' => true],
                ],
                'index' => [['user_id']],
            ],
            'drivers' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'name' => ['t' => 'string', 'len' => 64],
                    'type' => ['t' => 'string', 'len' => 16],
                    'provider' => ['t' => 'string', 'len' => 32],
                    'config' => ['t' => 'text', 'null' => true],
                    'status' => ['t' => 'bool', 'default' => '1'],
                    'sortable' => ['t' => 'int', 'default' => '0'],
                    'remark' => ['t' => 'string', 'len' => 255, 'null' => true],
                    'created_at' => ['t' => 'datetime', 'null' => true],
                    'updated_at' => ['t' => 'datetime', 'null' => true],
                ],
                'index' => [['type']],
            ],
            'group_storage' => [
                'cols' => [
                    'group_id' => ['t' => 'uint'],
                    'storage_id' => ['t' => 'uint'],
                ],
                'pk' => [['group_id', 'storage_id']],
            ],
            'group_driver' => [
                'cols' => [
                    'group_id' => ['t' => 'uint'],
                    'driver_id' => ['t' => 'uint'],
                ],
                'pk' => [['group_id', 'driver_id']],
            ],
            'storage_driver' => [
                'cols' => [
                    'storage_id' => ['t' => 'uint'],
                    'driver_id' => ['t' => 'uint'],
                ],
                'pk' => [['storage_id', 'driver_id']],
            ],
            'packages' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'name' => ['t' => 'string', 'len' => 64],
                    'group_id' => ['t' => 'uint', 'default' => '1'],
                    'price_cents' => ['t' => 'bigint', 'default' => '0'],
                    'duration_days' => ['t' => 'int', 'default' => '30'],
                    'capacity' => ['t' => 'bigint', 'default' => '0'],
                    'max_file_size' => ['t' => 'bigint', 'default' => '0'],
                    'daily_limit' => ['t' => 'int', 'default' => '0'],
                    // 每个用户可购买该套餐的次数上限，0 = 不限购
                    'purchase_limit' => ['t' => 'int', 'default' => '0'],
                    'description' => ['t' => 'string', 'len' => 500, 'null' => true],
                    'features' => ['t' => 'text', 'null' => true],
                    'status' => ['t' => 'bool', 'default' => '1'],
                    'sortable' => ['t' => 'int', 'default' => '0'],
                    'created_at' => ['t' => 'datetime', 'null' => true],
                    'updated_at' => ['t' => 'datetime', 'null' => true],
                ],
            ],
            'orders' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'order_no' => ['t' => 'string', 'len' => 32],
                    'user_id' => ['t' => 'uint'],
                    'package_id' => ['t' => 'uint'],
                    'package_name' => ['t' => 'string', 'len' => 64],
                    'amount_cents' => ['t' => 'bigint', 'default' => '0'],
                    'discount_cents' => ['t' => 'bigint', 'default' => '0'],
                    'total_cents' => ['t' => 'bigint', 'default' => '0'],
                    'coupon_id' => ['t' => 'uint', 'null' => true],
                    'driver_id' => ['t' => 'uint', 'null' => true],
                    'gateway' => ['t' => 'string', 'len' => 32, 'null' => true],
                    'trade_no' => ['t' => 'string', 'len' => 80, 'null' => true],
                    'status' => ['t' => 'bool', 'default' => '0'],
                    'paid_at' => ['t' => 'datetime', 'null' => true],
                    'expire_at' => ['t' => 'datetime', 'null' => true],
                    'created_at' => ['t' => 'datetime', 'null' => true],
                    'updated_at' => ['t' => 'datetime', 'null' => true],
                ],
                'unique' => [['order_no']],
                'index' => [['user_id'], ['status']],
            ],
            'coupons' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'code' => ['t' => 'string', 'len' => 32],
                    'name' => ['t' => 'string', 'len' => 64],
                    'type' => ['t' => 'string', 'len' => 10, 'default' => "'fixed'"],
                    'value' => ['t' => 'bigint', 'default' => '0'],
                    'min_amount_cents' => ['t' => 'bigint', 'default' => '0'],
                    'max_discount_cents' => ['t' => 'bigint', 'default' => '0'],
                    'quota' => ['t' => 'int', 'default' => '0'],
                    'used' => ['t' => 'int', 'default' => '0'],
                    'per_user' => ['t' => 'int', 'default' => '1'],
                    'scope' => ['t' => 'text', 'null' => true],
                    'starts_at' => ['t' => 'datetime', 'null' => true],
                    'expires_at' => ['t' => 'datetime', 'null' => true],
                    'status' => ['t' => 'bool', 'default' => '1'],
                    'created_at' => ['t' => 'datetime', 'null' => true],
                ],
                'unique' => [['code']],
            ],
            'coupon_uses' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'coupon_id' => ['t' => 'uint'],
                    'user_id' => ['t' => 'uint'],
                    'order_id' => ['t' => 'uint'],
                    'created_at' => ['t' => 'datetime', 'null' => true],
                ],
                'index' => [['coupon_id'], ['user_id']],
            ],
            'tickets' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'user_id' => ['t' => 'uint'],
                    'subject' => ['t' => 'string', 'len' => 200],
                    'category' => ['t' => 'string', 'len' => 32, 'default' => "'other'"],
                    'priority' => ['t' => 'tinyint', 'default' => '1'],
                    'status' => ['t' => 'tinyint', 'default' => '0'],
                    'last_reply_at' => ['t' => 'datetime', 'null' => true],
                    'created_at' => ['t' => 'datetime', 'null' => true],
                    'updated_at' => ['t' => 'datetime', 'null' => true],
                ],
                'index' => [['user_id'], ['status']],
            ],
            'ticket_replies' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'ticket_id' => ['t' => 'uint'],
                    'user_id' => ['t' => 'uint'],
                    'is_admin' => ['t' => 'bool', 'default' => '0'],
                    'content' => ['t' => 'text'],
                    'created_at' => ['t' => 'datetime', 'null' => true],
                ],
                'index' => [['ticket_id']],
            ],
            'pages' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'title' => ['t' => 'string', 'len' => 128],
                    'slug' => ['t' => 'string', 'len' => 64],
                    'content' => ['t' => 'mediumtext', 'null' => true],
                    'status' => ['t' => 'bool', 'default' => '1'],
                    'sortable' => ['t' => 'int', 'default' => '0'],
                    'show_in_footer' => ['t' => 'bool', 'default' => '1'],
                    'created_at' => ['t' => 'datetime', 'null' => true],
                    'updated_at' => ['t' => 'datetime', 'null' => true],
                ],
                'unique' => [['slug']],
            ],
            'reports' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'image_id' => ['t' => 'uint', 'null' => true],
                    'album_id' => ['t' => 'uint', 'null' => true],
                    'reporter_id' => ['t' => 'uint', 'null' => true],
                    'category' => ['t' => 'string', 'len' => 32, 'default' => "'other'"],
                    'reason' => ['t' => 'string', 'len' => 500, 'null' => true],
                    'status' => ['t' => 'tinyint', 'default' => '0'],
                    'handler_id' => ['t' => 'uint', 'null' => true],
                    'handle_note' => ['t' => 'string', 'len' => 500, 'null' => true],
                    'created_at' => ['t' => 'datetime', 'null' => true],
                    'handled_at' => ['t' => 'datetime', 'null' => true],
                ],
                'index' => [['status']],
            ],
            'feedbacks' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'user_id' => ['t' => 'uint', 'null' => true],
                    'kind' => ['t' => 'string', 'len' => 16, 'default' => "'suggestion'"],
                    'content' => ['t' => 'text'],
                    'contact' => ['t' => 'string', 'len' => 128, 'null' => true],
                    'status' => ['t' => 'tinyint', 'default' => '0'],
                    'reply' => ['t' => 'text', 'null' => true],
                    'created_at' => ['t' => 'datetime', 'null' => true],
                    'replied_at' => ['t' => 'datetime', 'null' => true],
                ],
            ],
            'notices' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'title' => ['t' => 'string', 'len' => 128],
                    'content' => ['t' => 'text', 'null' => true],
                    'level' => ['t' => 'string', 'len' => 16, 'default' => "'info'"],
                    'pinned' => ['t' => 'bool', 'default' => '0'],
                    'status' => ['t' => 'bool', 'default' => '1'],
                    'popup' => ['t' => 'bool', 'default' => '0'],
                    'expire_at' => ['t' => 'datetime', 'null' => true],
                    'created_at' => ['t' => 'datetime', 'null' => true],
                    'updated_at' => ['t' => 'datetime', 'null' => true],
                ],
            ],
            'user_oauth' => [
                'cols' => [
                    'id' => ['t' => 'id'],
                    'user_id' => ['t' => 'uint'],
                    'driver_id' => ['t' => 'uint', 'null' => true],
                    'provider' => ['t' => 'string', 'len' => 32],
                    'openid' => ['t' => 'string', 'len' => 128],
                    'unionid' => ['t' => 'string', 'len' => 128, 'null' => true],
                    'nickname' => ['t' => 'string', 'len' => 128, 'null' => true],
                    'avatar' => ['t' => 'string', 'len' => 255, 'null' => true],
                    'created_at' => ['t' => 'datetime', 'null' => true],
                ],
                'unique' => [['provider', 'openid']],
                'index' => [['user_id']],
            ],
        ];
    }
}
