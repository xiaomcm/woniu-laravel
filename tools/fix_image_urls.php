<?php
/**
 * 修复存量图片地址：把库里指向旧站点地址的绝对 URL 换成当前站点地址。
 *
 * 背景：
 *   images.url 与 images.thumb_url 存的是**绝对地址**，
 *   由上传那一刻的站点地址（config/installed.php 里的 app.url）拼出来。
 *   站点地址一变（换域名、换端口），历史图片的链接就全部指向旧地址 → 打不开 / 404。
 *   新上传的图片不受影响（上传时按当时的地址生成）。
 *
 *   本工具只改 images 表的 url 与 thumb_url 两列，且只替换 URL 的 origin，
 *   路径与查询串原样保留，绝不碰用户自己写的外链。
 *
 * 用法：
 *   php tools/fix_image_urls.php                        # 处理当前站点库，地址从 config 读
 *   php tools/fix_image_urls.php <库文件> [新站点地址]   # 处理指定库
 *   php tools/fix_image_urls.php --all [新站点地址]      # 所有已知库，统一用同一个地址
 *
 * 例：正式部署换了域名后
 *   php tools/fix_image_urls.php --all https://img.example.com
 */

declare(strict_types=1);

$root = dirname(__DIR__);

// ------------------------------------------------------------ 解析参数

$args = array_slice($argv, 1);
$newSite = '';
$dbFiles = [];
$all = false;

foreach ($args as $arg) {
    if ($arg === '--all') {
        $all = true;
        continue;
    }
    if ($dbFiles === [] && !$all) {
        $dbFiles[] = $arg;
        continue;
    }
    if ($newSite === '') {
        $newSite = $arg;
    }
}

/** 已知库：路径 + 它自己的站点配置文件 */
$known = [
    '当前站点' => [
        'db'     => $root . '/storage/database.sqlite',
        'config' => $root . '/config/installed.php',
    ],
];

if ($all) {
    foreach ($known as $k => $v) {
        $dbFiles[] = $v['db'];
    }
    // 全部用同一个新地址
    $configs = [];
} elseif ($dbFiles !== []) {
    $configs = [];
    foreach ($dbFiles as $f) {
        $configs[$f] = null;
    }
} else {
    foreach ($known as $k => $v) {
        $dbFiles[] = $v['db'];
        $configs[$v['db']] = $v['config'];
    }
}

// 新站点地址：参数优先，其次各自配置
if ($newSite !== '') {
    $newSite = rtrim($newSite, '/');
}

/** 从 installed.php 里读 app.url */
$siteFromConfig = static function (string $file): string {
    if (!is_file($file)) {
        return '';
    }
    $data = require $file;
    return rtrim((string) ($data['app']['url'] ?? ''), '/');
};

// ------------------------------------------------------------ 处理

$totalChanged = 0;

foreach ($dbFiles as $dbFile) {
    if (!is_file($dbFile)) {
        fwrite(STDERR, "跳过（库文件不存在）：{$dbFile}\n");
        continue;
    }

    $site = $newSite !== ''
        ? $newSite
        : $siteFromConfig($configs[$dbFile] ?? '');
    if ($site === '') {
        fwrite(STDERR, "跳过（读不到站点地址）：{$dbFile}\n");
        continue;
    }

    $pdo = new PDO('sqlite:' . $dbFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $newOrigin = (string) parse_url($site, PHP_URL_SCHEME) . '://' . (string) parse_url($site, PHP_URL_HOST)
        . (parse_url($site, PHP_URL_PORT) ? ':' . parse_url($site, PHP_URL_PORT) : '');

    echo "[" . basename(dirname(dirname($dbFile))) . "] {$dbFile}\n";
    echo "  当前站点地址: {$site}\n";

    $changed = 0;

    foreach (['url', 'thumb_url'] as $column) {
        $rows = $pdo->query(
            "SELECT id, \"{$column}\" AS v FROM `images` WHERE \"{$column}\" IS NOT NULL AND \"{$column}\" <> ''"
        )->fetchAll(PDO::FETCH_ASSOC);

        $update = $pdo->prepare(
            "UPDATE `images` SET \"{$column}\" = ? WHERE id = ?"
        );

        foreach ($rows as $row) {
            $old = (string) $row['v'];
            if (!preg_match('#^https?://#i', $old)) {
                continue;   // 相对地址不受站点地址影响
            }

            $parts = parse_url($old);
            if ($parts === false || !isset($parts['host'])) {
                continue;
            }

            $oldOrigin = $parts['scheme'] . '://' . $parts['host']
                . (isset($parts['port']) ? ':' . $parts['port'] : '');

            if ($oldOrigin === $newOrigin) {
                continue;   // 已经指向当前站点
            }

            // 只改 origin，路径与查询串原样保留
            $path = $parts['path'] ?? '';
            if (isset($parts['query'])) {
                $path .= '?' . $parts['query'];
            }
            $newValue = $newOrigin . $path;

            $update->execute([$newValue, (int) $row['id']]);
            $changed++;
            printf("    #%d %s\n        旧: %s\n        新: %s\n", $row['id'], $column, $old, $newValue);
        }
    }

    // ---------------------------------------------------------------- 补齐空 url
    //
    // images.url 存的是图片的绝对地址，正常上传时由存储驱动写入。
    // 但早期版本导入的示例图这一列是空的，页面直接取 $image['url']
    // 就会渲染成 <img src="">（浏览器里是裂图）。
    // 这里按 pathname 补一个能用的地址。
    //
    // 只有本地存储能这样补：云端存储的地址由各家的驱动生成，
    // 路径结构跟 /uploads/... 不一样，硬拼出来的地址是错的，宁可留空。

    $localStorageIds = [];
    foreach ($pdo->query('SELECT id, type, url FROM `storages`') as $s) {
        if ((string) $s['type'] === 'local') {
            $localStorageIds[(int) $s['id']] = trim((string) $s['url']);
        }
    }

    $emptyRows = $pdo->query(
        "SELECT id, pathname, storage_id FROM `images`
         WHERE (url IS NULL OR url = '') AND pathname <> ''"
    )->fetchAll(PDO::FETCH_ASSOC);

    $fill = $pdo->prepare('UPDATE `images` SET url = ? WHERE id = ?');

    foreach ($emptyRows as $row) {
        $storageId = (int) $row['storage_id'];
        if (!isset($localStorageIds[$storageId])) {
            echo "    #{$row['id']} url 为空，但存储不是本地存储，跳过（地址要由云端驱动生成）\n";
            continue;
        }

        // 存储配了自定义域名就用域名，否则用站点地址 + /uploads/
        $custom = $localStorageIds[$storageId];
        $newValue = $custom !== ''
            ? rtrim($custom, '/') . '/' . ltrim((string) $row['pathname'], '/')
            : $newOrigin . '/uploads/' . ltrim((string) $row['pathname'], '/');

        $fill->execute([$newValue, (int) $row['id']]);
        $changed++;
        printf("    #%d url 原本为空，已补齐\n        新: %s\n", $row['id'], $newValue);
    }

    echo "  改动 " . $changed . " 处\n\n";
    $totalChanged += $changed;
}

echo "合计改动 {$totalChanged} 处\n";
