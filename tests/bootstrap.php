<?php
/**
 * PHPUnit 的引导文件。
 *
 * 必须排在 vendor/autoload.php 之前加载 app/helpers.php：
 * Laravel 自带的助手函数都带 function_exists 守卫，谁先定义谁生效。
 * 先加载才能让 url() / asset() / redirect() / back() / csrf_field()
 * 是蜗牛图床自己那套实现，而不是 Laravel 默认的。
 *
 * 这与 public/index.php、artisan 的加载顺序保持一致。
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * 确保测试库文件存在。
 *
 * 测试库是独立的一份 SQLite（见 phpunit.xml 的 WONIU_DB_SQLITE），
 * 但它的目录与文件可能被清掉（清空测试数据、清理 storage 目录、首次 clone 后跑测试）。
 * Laravel 的 SQLiteConnector 不会自动建目录，文件不存在就直接抛
 * SQLiteDatabaseDoesNotExistException —— 23 个用例一起挂。
 *
 * 这里先建目录再建空文件，表的创建交给 DatabaseTestCase 去做。
 */
$testDb = $_ENV['WONIU_DB_SQLITE'] ?? $_SERVER['WONIU_DB_SQLITE'] ?? getenv('WONIU_DB_SQLITE');
if (!is_string($testDb) || $testDb === '' || $testDb === ':memory:') {
    $testDb = __DIR__ . '/../storage/testing/database.sqlite';
}
if (strpos($testDb, ':memory:') === false) {
    $testDbDir = dirname($testDb);
    if (!is_dir($testDbDir)) {
        @mkdir($testDbDir, 0775, true);
    }
    if (!is_file($testDb)) {
        @touch($testDb);
    }
}
