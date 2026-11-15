<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 *
 * 数据库配置。连接参数全部来自 config/installed.php，
 * 已装好的站点不需要改任何东西即可连上原来的库。
 */

declare(strict_types=1);

use Illuminate\Support\Str;

$woniu = require dirname(__DIR__) . '/bootstrap/woniu.php';
$db = $woniu['db'] ?? [];

$driver = (string) ($db['driver'] ?? 'mysql');
$prefix = (string) ($db['prefix'] ?? '');

// 测试环境允许 phpunit.xml 用 DB_CONNECTION 显式指定驱动。
// 干净 clone 里没有 config/installed.php，$db['driver'] 会回落到默认值 mysql，
// 没有 MySQL 服务时 23 个用例一起挂。生产环境不受影响（APP_ENV 不是 testing）。
$defaultDriver = $driver;
if (env('APP_ENV') === 'testing') {
    $override = (string) (env('DB_CONNECTION') ?: '');
    if ($override !== '') {
        $defaultDriver = $override;
    }
}

/**
 * SQLite 的库文件路径：既有实现 存的是相对路径（storage/database.sqlite），
 * 这里按项目根目录解析成绝对路径，和既有的 BASE_PATH 拼接规则一致。
 *
 * WONIU_DB_SQLITE 是给测试用的显式覆盖：指向别的库文件，
 * 跑测试时就不会碰到站点正在用的库。不设置时行为与既有行为完全一致。
 */
$sqlitePath = static function () use ($db): string {
    $override = (string) env('WONIU_DB_SQLITE', '');
    if ($override !== '') {
        if ($override === ':memory:' || preg_match('#^([a-zA-Z]:|/|\\\\)#', $override)) {
            return $override;
        }
        return dirname(__DIR__) . '/' . ltrim($override, '/');
    }

    $path = (string) ($db['sqlite_path'] ?? 'storage/database.sqlite');
    if (preg_match('#^([a-zA-Z]:|/|\\\\)#', $path)) {
        return $path;
    }
    return dirname(__DIR__) . '/' . ltrim($path, '/');
};

return [

    'default' => in_array($defaultDriver, ['mysql', 'sqlite', 'pgsql', 'sqlsrv'], true) ? $defaultDriver : 'mysql',

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => null,
            'database' => $sqlitePath(),
            'prefix' => '',
            // 与既有的 PRAGMA 设置保持一致
            'foreign_key_constraints' => true,
            'busy_timeout' => null,
            'journal_mode' => 'WAL',
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => null,
            'host' => (string) ($db['host'] ?? '127.0.0.1'),
            'port' => (string) ($db['port'] ?? '3306'),
            'database' => (string) ($db['database'] ?? ''),
            'username' => (string) ($db['username'] ?? ''),
            'password' => (string) ($db['password'] ?? ''),
            'unix_socket' => '',
            'charset' => (string) ($db['charset'] ?? 'utf8mb4'),
            'collation' => 'utf8mb4_unicode_ci',
            // 表前缀由 Laravel 连接层统一拼，业务代码里的 SQL 不带前缀
            'prefix' => $prefix,
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                \Pdo\Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => (string) ($db['host'] ?? env('DB_HOST', '127.0.0.1')),
            'port' => (string) ($db['port'] ?? env('DB_PORT', '5432')),
            'database' => (string) ($db['database'] ?? env('DB_DATABASE', 'forge')),
            'username' => (string) ($db['username'] ?? env('DB_USERNAME', 'forge')),
            'password' => (string) ($db['password'] ?? env('DB_PASSWORD', '')),
            'charset' => 'utf8',
            'prefix' => $prefix,
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => (string) ($db['host'] ?? env('DB_HOST', '127.0.0.1')),
            'port' => (string) ($db['port'] ?? env('DB_PORT', '1433')),
            'database' => (string) ($db['database'] ?? env('DB_DATABASE', 'forge')),
            'username' => (string) ($db['username'] ?? env('DB_USERNAME', 'forge')),
            'password' => (string) ($db['password'] ?? env('DB_PASSWORD', '')),
            'charset' => 'utf8',
            'prefix' => $prefix,
            'prefix_indexes' => true,
            'options' => defined('\\PDO::SQLSRV_ATTR_ENCODING')
                ? [\PDO::SQLSRV_ATTR_ENCODING => \PDO::SQLSRV_ENCODING_UTF8]
                : [],
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'woniu')) . '-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_base' => env('REDIS_BACKOFF_BASE', '100'),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', '1000'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_base' => env('REDIS_BACKOFF_BASE', '100'),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', '1000'),
        ],

    ],

];
