<?php
/**
 * 把站点登录账号统一改成 admin / admin。
 *
 * 背景：
 *   users 表的 name 与 email 都有 UNIQUE 索引（数据契约的一部分，不能动），
 *   所以「所有账号都叫 admin」在结构上只可能有一个 admin 账号。
 *   现有的第二个账号 id=3 是零引用的空壳测试用户（ albums / images / tokens /
 *   orders / tickets / logs … 全部扫过，对 user_id=3 的引用为 0 行），
 *   删掉它不会丢任何业务数据。
 *
 * 执行：
 *   1. 删除零引用的测试账号
 *   2. 管理员账号 name = admin、email = admin@woniu.local、password = password_hash('admin')
 *   3. 打印结果，验证 password_verify 通过
 *
 * 关于 email：
 *   email 列有 UNIQUE 索引，且是登录标识之一（见 User::findForLogin：邮箱查不到会
 *   回退按用户名精确匹配），所以登录名写 admin 不影响用 admin 登录。
 *   但 email 本身要是一个**合法邮箱** —— 用户下拉菜单头部显示的是账号绑定的邮箱，
 *   以前这里写的是 "admin"（等于把用户名塞进邮箱列），显示出来是「管理员 · admin」，
 *   既不是邮箱也跟上方昵称重复。现在统一写成 admin@woniu.local。
 *
 * 用法：php tools/reset_accounts.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);

$dbs = [
    '当前站点' => $root . '/storage/database.sqlite',
];

$loginName = 'admin';
$loginEmail = 'admin@woniu.local';
$plainPassword = 'admin';

foreach ($dbs as $label => $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "[{$label}] 找不到库文件，跳过：{$file}\n");
        continue;
    }

    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $pdo->beginTransaction();
    try {
        // 只删没有任何业务数据引用的账号
        $referenced = [];
        $tables = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            if ($table === 'users') {
                continue;
            }
            $cols = [];
            foreach ($pdo->query('PRAGMA table_info("' . $table . '")') as $col) {
                $cols[] = $col['name'];
            }
            foreach ($cols as $col) {
                if (!str_contains($col, 'user') && !str_contains($col, 'owner')) {
                    continue;
                }
                $n = (int) $pdo->query(
                    'SELECT COUNT(*) FROM "' . $table . '" WHERE "' . $col . '" = 3'
                )->fetchColumn();
                if ($n > 0) {
                    $referenced[] = "{$table}.{$col}={$n}";
                }
            }
        }

        if ($referenced !== []) {
            throw new RuntimeException(
                'id=3 被这些数据引用，不能删：' . implode(', ', $referenced)
            );
        }

        $pdo->exec('DELETE FROM `users` WHERE `id` = 3');

        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'UPDATE `users` SET `name` = ?, `email` = ?, `password` = ? WHERE `id` = 1'
        );
        $stmt->execute([$loginName, $loginEmail, $hash]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "[{$label}] 失败：" . $e->getMessage() . "\n");
        continue;
    }

    // ------------------------------------------------ 验证
    $rows = $pdo->query('SELECT id, name, email, is_admin, status, password FROM `users` ORDER BY id')
        ->fetchAll(PDO::FETCH_ASSOC);

    echo "[{$label}]\n";
    foreach ($rows as $row) {
        $ok = password_verify($plainPassword, (string) $row['password']);
        printf(
            "  #%d  name=%s  email=%s  admin=%s  status=%s  密码校验=%s\n",
            $row['id'],
            $row['name'],
            $row['email'],
            $row['is_admin'],
            $row['status'],
            $ok ? '通过' : '失败'
        );
    }
    echo "\n";
}
