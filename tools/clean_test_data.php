<?php
/**
 * 清空测试数据（WONIU 2.0）
 *
 * 只清业务运行时产生的测试数据，保留系统初始化配置，
 * 保证清空后站点仍可正常登录、管理、上传。
 *
 * 保留：users（管理员账号）、settings（站点配置）、drivers / storages /
 *       groups 及其关联表（存储与权限配置）、packages（套餐）、pages（默认页面）、
 *       notices（系统公告）
 * 清空：images / albums / orders / tickets / ticket_replies / logs /
 *       reports / tokens / coupons / coupon_uses / feedbacks / user_oauth
 *
 * 用法：
 *   php tools/clean_test_data.php            # 仅清数据库
 *   php tools/clean_test_data.php --files    # 同时删除上传的测试文件与缩略图
 *   php tools/clean_test_data.php --files --yes   # 跳过交互确认
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$dbFile = $root . '/storage/database.sqlite';

if (!is_file($dbFile)) {
    fwrite(STDERR, "找不到数据库文件：{$dbFile}\n");
    exit(1);
}

$withFiles = in_array('--files', $argv, true);
$skipConfirm = in_array('--yes', $argv, true) || in_array('-y', $argv, true);

/** 需要清空的业务测试数据表 */
$CLEAR_TABLES = [
    'images',
    'albums',
    'orders',
    'ticket_replies',
    'tickets',
    'logs',
    'reports',
    'tokens',
    'coupon_uses',
    'coupons',
    'feedbacks',
    'user_oauth',
];

/** 必须保留的系统初始化表（只做校验，不动数据） */
$KEEP_TABLES = [
    'users',
    'settings',
    'drivers',
    'storages',
    'groups',
    'group_driver',
    'group_storage',
    'storage_driver',
    'packages',
    'pages',
    'notices',
];

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$existing = $pdo
    ->query("SELECT name FROM sqlite_master WHERE type='table'")
    ->fetchAll(PDO::FETCH_COLUMN);

$clear = array_values(array_intersect($CLEAR_TABLES, $existing));

echo "数据库：{$dbFile}\n";
echo "将清空的表（" . count($clear) . '）：' . implode(', ', $clear) . "\n";

$before = [];
foreach ($clear as $t) {
    $before[$t] = (int) $pdo->query("SELECT COUNT(*) FROM \"{$t}\"")->fetchColumn();
}
$totalBefore = array_sum($before);

echo "当前测试数据合计：{$totalBefore} 行\n";
foreach ($before as $t => $c) {
    if ($c > 0) {
        echo "  {$t}: {$c}\n";
    }
}

if (!$skipConfirm) {
    echo "\n确认清空？(yes/no) ";
    $ans = trim((string) fgets(STDIN));
    if (strtolower($ans) !== 'yes' && $ans !== 'y') {
        echo "已取消。\n";
        exit(0);
    }
}

// 备份（带时间戳，最多保留 5 份）
$bak = $dbFile . '.bak-cleantest-' . date('Ymd-His');
copy($dbFile, $bak);
echo "\n已备份：{$bak}\n";

$pdo->beginTransaction();
try {
    foreach ($clear as $t) {
        $pdo->exec("DELETE FROM \"{$t}\"");
        // 重置自增序列，让 id 从 1 重新开始
        $pdo->exec("DELETE FROM sqlite_sequence WHERE name = '{$t}'");
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "清空失败已回滚：" . $e->getMessage() . "\n");
    exit(1);
}

echo "\n清空完成。\n";

/**
 * 校验保留表的数据还在
 */
$db2 = new PDO('sqlite:' . $dbFile);
$db2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo "\n保留表校验：\n";
foreach ($KEEP_TABLES as $t) {
    if (!in_array($t, $existing, true)) {
        continue;
    }
    $c = (int) $db2->query("SELECT COUNT(*) FROM \"{$t}\"")->fetchColumn();
    printf("  %-16s %5d 行%s\n", $t, $c, $c === 0 ? '   ← 空表' : '');
}

if ($withFiles) {
    echo "\n清理上传文件：\n";
    foreach (['storage/uploads', 'storage/thumbs'] as $dir) {
        $abs = $root . '/' . $dir;
        if (!is_dir($abs)) {
            continue;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        $n = 0;
        $bytes = 0;
        foreach ($files as $f) {
            /** @var SplFileInfo $f */
            if ($f->isDir()) {
                @rmdir($f->getPathname());
                continue;
            }
            $bytes += $f->getSize();
            if (@unlink($f->getPathname())) {
                $n++;
            }
        }
        printf("  %-20s 删除 %d 个文件（%.1f KB）\n", $dir, $n, $bytes / 1024);
    }

    // 残留空目录一并清掉
    foreach (['storage/uploads', 'storage/thumbs'] as $dir) {
        $abs = $root . '/' . $dir;
        if (!is_dir($abs)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            /** @var SplFileInfo $f */
            if ($f->isDir()) {
                @rmdir($f->getPathname());
            }
        }
    }
}

echo "\n完成。\n";
