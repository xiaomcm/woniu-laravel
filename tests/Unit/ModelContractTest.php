<?php

namespace Tests\Unit;

use App\Core\Database;
use App\Core\Schema;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

/**
 * 模型行为契约测试。
 *
 * 锁死既有的模型对外契约 —— 这些是业务代码（控制器、模板、服务层）依赖的接口：
 *   - find() 返回行数组（不是 Eloquent 模型对象）
 *   - create() 返回新记录的主键 id（不是模型对象）
 *   - 静态方法名与签名一字不改
 *
 * 底层换成 Eloquent 之后如果不小心让 find() 返回模型对象，
 * 模板里的 $image['sha1'] 会全线崩掉。这个测试就是防这个的。
 */
class ModelContractTest extends TestCase
{
    private static ?Container $container = null;
    private static ?Capsule $capsule = null;
    private ?\PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();

        // 独立的容器 + 内存库，不碰站点真实数据
        $container = new Container();
        Container::setInstance($container);
        Facade::setFacadeApplication($container);

        $capsule = new Capsule($container);
        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ], 'default');
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $container->instance('db', $capsule->getDatabaseManager());

        $pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        // 让 Eloquent 与 App\Core\Database 共用同一个 PDO 句柄
        $capsule->getConnection()->setPdo($pdo);
        $container->instance('db.connection', $capsule->getConnection());

        self::$container = $container;
        self::$capsule = $capsule;

        Database::attach($pdo, 'sqlite', '');

        Schema::install($pdo, 'sqlite', '');

        $this->pdo = $pdo;
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        self::$capsule = null;
        self::$container = null;
        parent::tearDown();
    }

    private function seedUser(): int
    {
        $id = (int) $this->pdo->lastInsertId();
        return $id;
    }

    public function test_find_returns_a_plain_row_array(): void
    {
        $this->pdo->exec(
            "INSERT INTO `users` (`name`,`email`,`password`,`group_id`,`is_admin`,`status`,`created_at`)
             VALUES ('阿岩','a@b.c','x',1,1,1,'2026-01-01 00:00:00')"
        );

        $row = \App\Models\User::find(1);

        $this->assertIsArray($row, 'find() 必须返回行数组，不能返回 Eloquent 模型对象');
        $this->assertSame('阿岩', $row['name']);
        $this->assertSame('a@b.c', $row['email']);
        $this->assertSame(1, (int) $row['is_admin']);
    }

    public function test_find_returns_null_when_not_found(): void
    {
        $this->assertNull(\App\Models\User::find(999999));
    }

    public function test_create_returns_the_primary_key_id(): void
    {
        $id = \App\Models\User::create([
            'name'       => '路人',
            'email'      => 'd@e.f',
            'password'   => 'x',
            'group_id'   => 1,
            'is_admin'   => 0,
            'status'     => 1,
            'created_at' => '2026-01-01 00:00:00',
        ]);

        $this->assertIsInt($id, 'create() 必须返回主键 id，不能返回模型对象');
        $this->assertGreaterThan(0, $id);

        $row = \App\Models\User::find($id);
        $this->assertSame('路人', $row['name']);
    }

    public function test_image_find_returns_row_array_with_sha1(): void
    {
        $id = \App\Models\Image::create([
            'user_id'       => 1,
            'album_id'      => 1,
            'storage_id'    => 1,
            'name'          => '测试图',
            'original_name' => 'test.png',
            'pathname'      => '2026/01/01/abc.png',
            'mimetype'      => 'image/png',
            'extension'     => 'png',
            'size'          => 2048,
            'sha1'          => 'abc123',
            'md5'           => 'md5abc',
            'width'         => 100,
            'height'        => 200,
            'url'           => 'https://x/y.png',
            'status'        => 1,
            'is_public'     => 1,
        ]);

        $this->assertIsInt($id);
        $row = \App\Models\Image::find($id);
        $this->assertIsArray($row);
        $this->assertSame('abc123', $row['sha1']);
        $this->assertSame(2048, (int) $row['size']);
    }

    public function test_update_by_id_updates_the_row(): void
    {
        $id = \App\Models\Image::create([
            'user_id'       => 1,
            'album_id'      => 1,
            'storage_id'    => 1,
            'name'          => '测试图',
            'original_name' => 'test.png',
            'pathname'      => '2026/01/01/abc.png',
            'mimetype'      => 'image/png',
            'extension'     => 'png',
            'size'          => 2048,
            'url'           => 'https://x/y.png',
            'status'        => 1,
            'is_public'     => 1,
        ]);

        \App\Models\Notice::updateById(
            $id,
            ['title' => '改名了']
        );

        // updateById 的返回值契约：void
        $row = \App\Models\Notice::find($id);
        $this->assertSame('改名了', $row['title']);
    }

    public function test_update_by_id_is_a_noop_for_missing_row(): void
    {
        // 对不存在的 id 静默返回，不报错
        \App\Models\Notice::updateById(999999, ['title' => 'x']);
        $this->assertNull(\App\Models\Notice::find(999999));
    }

    public function test_delete_by_id_removes_the_row(): void
    {
        $id = \App\Models\Notice::create([
            'title'      => '公告',
            'content'    => '内容',
            'level'      => 'info',
            'pinned'     => 0,
            'status'     => 1,
            'created_at' => '2026-01-01 00:00:00',
        ]);

        // deleteById 的返回值契约：void
        \App\Models\Notice::deleteById($id);
        $this->assertNull(\App\Models\Notice::find($id));
    }

    public function test_all_rows_returns_array_of_row_arrays(): void
    {
        // Schema::install 会预置一条公告种子数据，这里按增量算
        $before = count(\App\Models\Notice::allRows());

        $this->pdo->exec(
            "INSERT INTO `notices` (`title`,`content`,`level`,`pinned`,`status`,`created_at`)
             VALUES ('公告一','c','info',0,1,'2026-01-01 00:00:00')"
        );
        $this->pdo->exec(
            "INSERT INTO `notices` (`title`,`content`,`level`,`pinned`,`status`,`created_at`)
             VALUES ('公告二','c','info',0,1,'2026-01-01 00:00:00')"
        );

        $rows = \App\Models\Notice::allRows();

        $this->assertIsArray($rows);
        $this->assertCount($before + 2, $rows);
        $this->assertIsArray($rows[0]);
        $this->assertArrayHasKey('title', $rows[0]);
    }

    public function test_model_does_not_use_automatic_timestamps(): void
    {
        // created_at / updated_at 是业务层手写的，
        // 如果 Eloquent 自动填，会写成 ISO8601 格式，破坏既有格式
        $this->assertFalse((new \App\Models\User())->usesTimestamps());
    }

    public function test_database_helper_writes_datetime_in_sql_format(): void
    {
        // now() 在 Laravel 里返回 Carbon，直接绑进 DATETIME 会写成 ISO8601，
        // 这里必须收敛成 SQL 的 'Y-m-d H:i:s'
        Database::insert('notices', [
            'title'      => '带时间',
            'content'    => 'c',
            'level'      => 'info',
            'pinned'     => 0,
            'status'     => 1,
            'created_at' => now(),
        ]);

        $value = (string) $this->pdo->query('SELECT created_at FROM `notices` LIMIT 1')->fetchColumn();
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $value,
            '时间戳格式必须是 Y-m-d H:i:s'
        );
    }

    public function test_database_table_prefix_is_applied(): void
    {
        $this->assertSame('`notices`', Database::table('notices'));
    }

    public function test_like_escapes_wildcards(): void
    {
        $this->assertSame('%a\\%b\\_c%', Database::like('a%b_c'));
    }

    public function test_transaction_rolls_back_on_exception(): void
    {
        // Schema::install 预置了种子数据，这里按增量算
        $before = (int) $this->pdo->query('SELECT COUNT(*) FROM `notices`')->fetchColumn();

        try {
            Database::transaction(function (\PDO $pdo): void {
                $pdo->exec("INSERT INTO `notices` (`title`,`content`,`level`,`pinned`,`status`,`created_at`)
                            VALUES ('回滚','c','info',0,1,'2026-01-01 00:00:00')");
                throw new \RuntimeException('boom');
            });
            $this->fail('异常应该往外抛');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $after = (int) $this->pdo->query('SELECT COUNT(*) FROM `notices`')->fetchColumn();
        $this->assertSame($before, $after, '事务回滚后不能留下数据');
    }

    public function test_transaction_commits_on_success(): void
    {
        $before = (int) $this->pdo->query('SELECT COUNT(*) FROM `notices`')->fetchColumn();

        Database::transaction(function (\PDO $pdo): void {
            $pdo->exec("INSERT INTO `notices` (`title`,`content`,`level`,`pinned`,`status`,`created_at`)
                        VALUES ('提交','c','info',0,1,'2026-01-01 00:00:00')");
        });

        $after = (int) $this->pdo->query('SELECT COUNT(*) FROM `notices`')->fetchColumn();
        $this->assertSame($before + 1, $after);
    }
}
