<?php

namespace Tests\Unit;

use App\Core\Schema;
use App\Core\SchemaExtra;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 表结构契约测试。
 *
 * 这是「换框架后用户数据百分百保留」最核心的一条保证：
 * 建表定义必须与既有契约逐字段一致。
 *
 * Schema / SchemaExtra / Migrator 三个文件是从既有实现原样搬过来的，
 * 本测试锁死它们的对外契约：表名集合、每张表的列、索引。
 * 一旦有人改动建表定义让旧库无法沿用，这个测试立刻红。
 */
class SchemaContractTest extends TestCase
{
    private const EXPECTED_TABLES = [
        'albums',
        'coupon_uses',
        'coupons',
        'drivers',
        'feedbacks',
        'group_driver',
        'group_storage',
        'groups',
        'images',
        'logs',
        'notices',
        'orders',
        'packages',
        'pages',
        'reports',
        'settings',
        'storage_driver',
        'storages',
        'ticket_replies',
        'tickets',
        'tokens',
        'user_oauth',
        'users',
    ];

    /**
     * 关键表的列名顺序。列名与顺序都锁死，避免有人误删/重命名导致老数据读不出来。
     */
    private const EXPECTED_COLUMNS = [
        'users' => [
            'id', 'name', 'email', 'password', 'group_id', 'capacity',
            'group_expired_at', 'is_admin', 'status', 'avatar', 'phone',
            'api_enabled',
            'created_at', 'updated_at', 'last_login_at',
        ],
        'images' => [
            'id', 'user_id', 'album_id', 'storage_id', 'name', 'original_name',
            'description', 'tags', 'pathname', 'mimetype', 'extension', 'size',
            'md5', 'sha1', 'width', 'height', 'url', 'thumb_url', 'views',
            'status', 'is_public', 'deleted_at', 'created_at', 'updated_at',
        ],
        'albums' => [
            'id', 'user_id', 'name', 'intro', 'cover', 'image_num',
            'is_public', 'key', 'created_at', 'updated_at',
        ],
        'groups' => [
            'id', 'name', 'is_default', 'capacity', 'max_file_size',
            'allowed_extensions', 'default_storage_id', 'max_upload_num',
            'daily_limit', 'permissions', 'watermark', 'created_at', 'updated_at',
        ],
        'storages' => [
            'id', 'name', 'type', 'config', 'url', 'is_default',
            'status', 'created_at', 'updated_at',
        ],
        'tokens' => [
            'id', 'user_id', 'name', 'token_hash', 'token_prefix',
            'last_used_at', 'expires_at', 'created_at',
        ],
        'settings' => ['key', 'value'],
        'logs' => ['id', 'user_id', 'action', 'ip', 'ua', 'detail', 'created_at'],
    ];

    private ?\PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        Schema::install($this->pdo, 'sqlite', '');
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        parent::tearDown();
    }

    public function test_it_creates_exactly_the_expected_tables(): void
    {
        $tables = $this->tables();

        $expected = self::EXPECTED_TABLES;
        sort($expected);
        $actual = array_keys($tables);
        sort($actual);

        $this->assertSame($expected, $actual, '表名集合与既有契约不一致');
        $this->assertCount(23, $tables, '契约锁定 23 张表');
    }

    #[DataProvider('criticalTableProvider')]
    public function test_critical_table_columns_match_the_contract(string $table, array $expectedColumns): void
    {
        $actual = [];
        foreach ($this->pdo->query('PRAGMA table_info(`' . $table . '`)') as $col) {
            $actual[] = (string) $col['name'];
        }

        $this->assertSame($expectedColumns, $actual, $table . ' 的列与既有契约不一致');
    }

    public static function criticalTableProvider(): array
    {
        $cases = [];
        foreach (self::EXPECTED_COLUMNS as $table => $columns) {
            $cases[$table] = [$table, $columns];
        }
        return $cases;
    }

    public function test_settings_table_uses_key_as_primary_key(): void
    {
        $pk = null;
        foreach ($this->pdo->query('PRAGMA table_info(`settings`)') as $col) {
            if ((int) $col['pk'] > 0) {
                $pk = (string) $col['name'];
            }
        }
        $this->assertSame('key', $pk, 'settings 表的主键必须是 key');
    }

    public function test_users_email_and_name_are_unique(): void
    {
        $indexes = [];
        foreach ($this->pdo->query('PRAGMA index_list(`users`)') as $idx) {
            $cols = [];
            foreach ($this->pdo->query('PRAGMA index_info(`' . $idx['name'] . '`)') as $ic) {
                if ($ic['name'] !== null) {
                    $cols[] = (string) $ic['name'];
                }
            }
            $indexes[(string) $idx['name']] = ['unique' => (int) $idx['unique'], 'columns' => $cols];
        }

        $this->assertArrayHasKey('users_email', $indexes);
        $this->assertSame(1, $indexes['users_email']['unique']);
        $this->assertSame(['email'], $indexes['users_email']['columns']);

        $this->assertArrayHasKey('users_name', $indexes);
        $this->assertSame(1, $indexes['users_name']['unique']);
    }

    public function test_install_seeds_default_group_storage_and_settings(): void
    {
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM `groups`')->fetchColumn());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM `storages`')->fetchColumn());
        $this->assertGreaterThan(
            0,
            (int) $this->pdo->query('SELECT COUNT(*) FROM `settings`')->fetchColumn(),
            'settings 表要写入默认设置项'
        );
        // 默认存储要是 local 类型
        $type = (string) $this->pdo->query('SELECT type FROM `storages` LIMIT 1')->fetchColumn();
        $this->assertSame('local', $type);
    }

    public function test_schema_extra_tables_are_also_created(): void
    {
        // SchemaExtra 单独负责的表（第二段建表）
        foreach (['drivers', 'group_storage', 'group_driver', 'storage_driver', 'packages', 'pages', 'notices', 'orders', 'coupons', 'coupon_uses', 'tickets', 'ticket_replies', 'reports', 'feedbacks', 'user_oauth'] as $table) {
            $this->assertArrayHasKey($table, $this->tables(), '缺表 ' . $table);
        }
    }

    public function test_mysql_statements_are_also_defined(): void
    {
        // MySQL 分支不能因为重构而丢失，否则 MySQL 站点装不上
        $statements = (function () {
            // 反射取私有的 statements 方法
            $ref = new \ReflectionMethod(Schema::class, 'statements');
            $ref->setAccessible(true);
            return $ref->invoke(null, 'mysql', '');
        })();

        $this->assertNotEmpty($statements);
        $joined = implode("\n", $statements);
        $this->assertStringContainsString('`users`', $joined);
        $this->assertStringContainsString('ENGINE=InnoDB', $joined);
        $this->assertStringContainsString('utf8mb4', $joined);
    }

    public function test_schema_extra_returns_mysql_statements_too(): void
    {
        $mysql = SchemaExtra::tables('mysql', 'p_');
        $sqlite = SchemaExtra::tables('sqlite', 'p_');

        $this->assertNotEmpty($mysql);
        $this->assertNotEmpty($sqlite);
        $this->assertStringContainsString('`p_drivers`', implode("\n", $mysql));
        $this->assertStringContainsString('`p_drivers`', implode("\n", $sqlite));
    }

    /**
     * @return array<string, array{type: string, name: string}>
     */
    private function tables(): array
    {
        $tables = [];
        $rows = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        )->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $tables[(string) $row['name']] = $row;
        }
        return $tables;
    }
}
