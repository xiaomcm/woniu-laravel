<?php

namespace Tests;

use App\Core\Schema;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

/**
 * 需要真实数据库的测试基座。
 *
 * 测试库是独立的一份 SQLite 文件（见 phpunit.xml 的 WONIU_DB_SQLITE），
 * 每个测试类开始前重建一次：建表 + 写入种子数据。
 * 站点正在用的 storage/database.sqlite 完全不受影响。
 */
abstract class DatabaseTestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // 上一轮用例留下的连接缓存要先清掉
        \App\Core\Database::reset();
        \App\Core\Setting::reset();
        \App\Core\Auth::reset();

        // 启动应用（配置要加载，ServiceProvider 会读库）
        parent::setUp();

        // 再重建测试库，让应用后续的请求用到干净的数据
        $this->rebuildTestDatabase();
    }

    /**
     * 重建测试库：删掉所有表 → 重新建表 → 写种子数据。
     *
     * 这里不删库文件：应用启动时已经建好了连接，删文件的话连接还指着旧 inode，
     * 后面写的还是被删掉的那个库。改成在同一个连接上把表全drop掉再建，
     * 连接与文件始终是同一个。
     */
    protected function rebuildTestDatabase(): void
    {
        $pdo = DB::connection()->getPdo();

        // 关掉外键约束，否则 drop 顺序不对会失败
        try {
            $pdo->exec('PRAGMA foreign_keys = OFF');
        } catch (\Throwable $e) {
            // MySQL 不支持这个 pragma，忽略
        }

        $tables = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        )->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            try {
                $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
            } catch (\Throwable $e) {
                // 忽略单个表的失败，继续删下一个
            }
        }

        Schema::install($pdo, 'sqlite', '');

        // Setting 的缓存里还留着上一轮的 schema_version，
        // Migrator 读到 >= VERSION 就直接返回，增量列（albums.password、
        // images.password、groups.buyable 等）就加不上。先清缓存再跑。
        \App\Core\Setting::reset();

        // Migrator 的增量升级列不是 Schema::install 建的，是每次请求前由 Migrator 补上的。
        // 生产的 AppServiceProvider 会在 boot 时跑一次 Migrator，测试环境这里补上。
        \App\Core\Migrator::run();

        try {
            $pdo->exec('PRAGMA foreign_keys = ON');
        } catch (\Throwable $e) {
            // 忽略
        }
    }

    /**
     * 插入一个用户，返回用户 id。
     */
    protected function createUser(
        string $name = 'tester',
        string $email = 'tester@example.com',
        string $password = 'Test123456',
        bool $isAdmin = false
    ): int {
        $id = DB::table('users')->insertGetId([
            'name'       => $name,
            'email'      => $email,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'group_id'   => 1,
            'is_admin'   => $isAdmin ? 1 : 0,
            'status'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $id;
    }

    /**
     * 插入一个图片，返回图片 id。
     */
    protected function createImage(int $userId = 1, string $sha1 = ''): int
    {
        $sha1 = $sha1 !== '' ? $sha1 : sha1((string) random_int(1, PHP_INT_MAX) . microtime(true));

        $id = DB::table('images')->insertGetId([
            'user_id'       => $userId,
            'album_id'      => null,
            'storage_id'    => 1,
            'name'          => '测试图',
            'original_name' => 'test.png',
            'pathname'      => '2026/01/01/' . $sha1 . '.png',
            'mimetype'      => 'image/png',
            'extension'     => 'png',
            'size'          => 2048,
            'md5'           => md5($sha1),
            'sha1'          => $sha1,
            'width'         => 800,
            'height'        => 600,
            'url'           => '/uploads/2026/01/01/' . $sha1 . '.png',
            'status'        => 1,
            'is_public'     => 1,
            'views'         => 0,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        return (int) $id;
    }
}
