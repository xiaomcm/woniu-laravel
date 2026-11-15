<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Core;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB as LaravelDB;

/**
 * 数据访问适配层。
 *
 * 业务代码（Models / Services / Controllers）里成片的 DB::fetch / DB::update 调用
 * 全部收敛到这里，底层换成 Laravel 的数据库连接。
 *
 * 关键点：这里取的是 Laravel 连接自己的 PDO 句柄，和 Eloquent 用的是同一个对象，
 * 所以裸 SQL 与 Eloquent 查询跑在同一事务、同一个连接上，SQLite 也不会因为
 * 两个连接互相抢锁而报 "database is locked"。
 */
class Database
{
    private static ?Connection $connection = null;

    /**
     * 兼容既有的 boot()：原先是自己 new PDO，现在连接由 Laravel 容器管，
     * 这里只保证连接已经就绪。
     */
    public static function boot(array $cfg = []): void
    {
        self::connection();
    }

    public static function connection(): Connection
    {
        if (!self::$connection instanceof Connection) {
            self::$connection = LaravelDB::connection();
        }
        return self::$connection;
    }

    /**
     * 清空连接缓存。
     *
     * 连接是进程内单例。单元测试里每个用例都会重建应用与数据库连接，
     * 不清缓存的话后面会用着上一个用例的连接。
     */
    public static function reset(): void
    {
        self::$connection = null;
    }

    /**
     *原先保留的接口：安装向导里用外部 PDO 接管数据库。
     * Laravel 侧改成直接把 PDO 绑到默认连接上。
     */
    public static function attach(\PDO $pdo, string $driver = 'mysql', string $prefix = ''): void
    {
        $connection = LaravelDB::connection();
        $connection->setPdo($pdo);
        self::$connection = $connection;
    }

    public static function pdo(): \PDO
    {
        return self::connection()->getPdo();
    }

    public static function driver(): string
    {
        return self::connection()->getDriverName();
    }

    public static function prefix(): string
    {
        return (string) self::connection()->getTablePrefix();
    }

    /**
     * 表名（带反引号与前缀）。
     *
     * 前缀在这里手工拼好，因为走的是裸 SQL，Laravel 的查询构造器不会替我们加前缀；
     * 而 Eloquent 那边由连接层统一加。两边最终落到同一张表。
     */
    public static function table(string $name): string
    {
        return '`' . self::prefix() . $name . '`';
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        //既有的 PDO 默认取数模式是 FETCH_ASSOC，这里保持一致
        $stmt->setFetchMode(\PDO::FETCH_ASSOC);
        foreach ($params as $key => $value) {
            // now() 在 Laravel 里返回 Carbon，直接绑进 DATETIME 会写成 ISO8601，
            // 这里统一收敛成既有的 'Y-m-d H:i:s'，数据库里的时间格式与旧版完全一致
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }
            $name = is_int($key) ? $key + 1 : (strncmp((string) $key, ':', 1) === 0 ? $key : ':' . $key);
            $type = \PDO::PARAM_STR;
            if (is_int($value)) {
                $type = \PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = \PDO::PARAM_BOOL;
            } elseif ($value === null) {
                $type = \PDO::PARAM_NULL;
            }
            $stmt->bindValue($name, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function value(string $sql, array $params = [])
    {
        $row = self::query($sql, $params)->fetch(\PDO::FETCH_NUM);
        return $row === false ? null : $row[0];
    }

    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $fields = implode(', ', array_map(static fn ($c) => '`' . $c . '`', $columns));
        $holders = implode(', ', array_map(static fn ($c) => ':' . $c, $columns));
        self::query(
            'INSERT INTO ' . self::table($table) . ' (' . $fields . ') VALUES (' . $holders . ')',
            $data
        );
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        if ($data === []) {
            return 0;
        }
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = '`' . $column . '` = :' . $column;
        }
        $params = [];
        foreach ($data as $key => $value) {
            $params['w_' . $key] = $value;
        }

        $sql = 'UPDATE ' . self::table($table) . ' SET ' . implode(', ', array_map(
            static fn ($set) => preg_replace('/:(\w+)/', ':s_$1', $set),
            $sets
        )) . ' WHERE ' . $where;

        $bound = [];
        foreach ($data as $key => $value) {
            $bound['s_' . $key] = $value;
        }
        foreach ($whereParams as $key => $value) {
            $bound[$key] = $value;
        }
        return self::query($sql, $bound)->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::query('DELETE FROM ' . self::table($table) . ' WHERE ' . $where, $params)->rowCount();
    }

    /**
     * 事务。直接开在共享 PDO 上，跟 Eloquent 用的是同一份事务状态。
     */
    public static function transaction(callable $callback)
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            // 已经在外层事务里，PDO 不支持嵌套，这里直接执行，由外层统一提交/回滚
            return $callback($pdo);
        }
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function like(string $keyword): string
    {
        return '%' . str_replace(['%', '_'], ['\%', '\_'], $keyword) . '%';
    }
}
