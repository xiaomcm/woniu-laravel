<?php
/**
 * 修复存量缩略图：把老命名规则的缩略图迁移到当前的 {sha1} 命名，并同步数据库里的地址。
 *
 * 背景：
 *   缩略图的命名规则是 `{sha1 前 2 位}/{sha1}.jpg`（见 UploadService::makeThumbnail）。
 *   ImageController::thumb() 也按文件名当 sha1 去查图片记录、判断可见性（分享密码、
 *   下线、审核状态），所以文件名与图片的 sha1 对不上就一律 404。
 *
 *   早期版本的缩略图用的是另一套哈希命名，数据库里的 thumb_url 还指着那些老路径，
 *   于是图库列表里的缩略图全部打不开。这是历史数据问题，代码逻辑本身没有改动。
 *
 * 本工具做三件事：
 *   1. 把老文件复制/移动到 {sha1} 命名的位置（文件已存在则跳过）
 *   2. 老文件也没有的，用原图按当前缩略图宽度重新生成
 *   3. 更新 images.thumb_url 为当前站点地址 + 正确的缩略图路径
 *
 * 用法：
 *   php tools/fix_thumbnails.php                          # 处理当前站点库
 *   php tools/fix_thumbnails.php <库文件> <storage根> [站点地址]
 *
 * 站点地址默认从该库同目录下的 config/installed.php 读。
 */

declare(strict_types=1);

$root = dirname(__DIR__);

$known = [
    '当前站点' => [
        'db'      => $root . '/storage/database.sqlite',
        'storage' => $root . '/storage',
        'config'  => $root . '/config/installed.php',
    ],
];

$args = array_slice($argv, 1);

if ($args !== []) {
    $dbFile = $args[0];
    $storageRoot = $args[1] ?? dirname(dirname($dbFile));
    $siteOverride = $args[2] ?? '';
    $targets = ['指定库' => ['db' => $dbFile, 'storage' => $storageRoot, 'config' => '']];
} else {
    $targets = $known;
    $siteOverride = '';
}

/** 从 installed.php 里读 app.url */
$siteFromConfig = static function (string $file): string {
    if ($file === '' || !is_file($file)) {
        return '';
    }
    $data = require $file;
    return rtrim((string) ($data['app']['url'] ?? ''), '/');
};

/** 用 GD 按缩略图宽度重新生成一张缩略图 */
$regenerate = static function (string $sourceFile, string $targetFile, int $width): bool {
    if (!is_file($sourceFile)) {
        return false;
    }
    if (!function_exists('imagecreatefromstring')) {
        return false;
    }
    $binary = @file_get_contents($sourceFile);
    if ($binary === false || $binary === '') {
        return false;
    }
    $src = @imagecreatefromstring($binary);
    if ($src === false) {
        return false;
    }
    $w = imagesx($src);
    $h = imagesy($src);
    if ($w <= 0 || $h <= 0) {
        imagedestroy($src);
        return false;
    }
    $newW = $width;
    $newH = max(1, (int) round($h * $width / $w));
    $dst = imagecreatetruecolor($newW, $newH);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
    $dir = dirname($targetFile);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        imagedestroy($src);
        imagedestroy($dst);
        return false;
    }
    $ok = @imagejpeg($dst, $targetFile, 85);
    imagedestroy($src);
    imagedestroy($dst);
    return $ok;
};

$thumbWidth = 360;
$totalFixed = 0;
$totalCleared = 0;

foreach ($targets as $label => $t) {
    $dbFile = $t['db'];
    if (!is_file($dbFile)) {
        fwrite(STDERR, "跳过（库文件不存在）：{$dbFile}\n");
        continue;
    }

    $site = $siteOverride !== '' ? rtrim($siteOverride, '/') : $siteFromConfig($t['config'] ?? '');
    if ($site === '') {
        fwrite(STDERR, "跳过（读不到站点地址）：{$dbFile}\n");
        continue;
    }

    $thumbsDir = rtrim($t['storage'], '/') . '/thumbs';
    $uploadsDir = rtrim($t['storage'], '/') . '/uploads';

    $pdo = new PDO('sqlite:' . $dbFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    echo "[{$label}] {$dbFile}\n";
    echo "  站点地址: {$site}\n";
    echo "  缩略图目录: {$thumbsDir}\n\n";

    $rows = $pdo->query(
        'SELECT id, sha1, pathname, thumb_url, deleted_at FROM `images` ORDER BY id'
    )->fetchAll(PDO::FETCH_ASSOC);

    $update = $pdo->prepare('UPDATE `images` SET `thumb_url` = ? WHERE id = ?');
    $fixed = 0;
    $cleared = 0;

    foreach ($rows as $row) {
        $sha1 = (string) $row['sha1'];
        if ($sha1 === '') {
            continue;
        }

        // 已删除的图片不再处理：它的缩略图本来就取不到，动它没有意义
        if (trim((string) $row['deleted_at']) !== '') {
            continue;
        }

        $currentThumb = (string) $row['thumb_url'];
        $currentName = '';
        if (preg_match('~/thumbs/([^/?#]+)/([^/?#]+)~', $currentThumb, $m)) {
            $currentName = $m[2];
        }

        // 已经按 sha1 命名且文件在位 → 只需确认地址里的 origin 对不对
        $targetName = $sha1 . '.jpg';
        $targetFile = $thumbsDir . '/' . substr($sha1, 0, 2) . '/' . $targetName;

        if (!is_file($targetFile)) {
            // 老文件搬过来
            $moved = false;
            if ($currentName !== '' && $currentName !== $targetName) {
                $oldFile = $thumbsDir . '/' . substr($currentName, 0, 2) . '/' . $currentName;
                if (is_file($oldFile)) {
                    $dir = dirname($targetFile);
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    $moved = @copy($oldFile, $targetFile);
                }
            }
            if (!$moved) {
                // 老文件也没有 → 用原图重新生成
                $source = $uploadsDir . '/' . ltrim((string) $row['pathname'], '/');
                $moved = $regenerate($source, $targetFile, $thumbWidth);
                if ($moved) {
                    echo "    #{$row['id']} 原图重新生成缩略图\n";
                }
            }
            if (!$moved) {
                // 补不出来（云端存储、格式不支持等）→ 清空地址，页面走占位图回退
                if ($currentThumb !== '') {
                    $update->execute(['', (int) $row['id']]);
                    $cleared++;
                    echo "    #{$row['id']} 补不出缩略图，已清空 thumb_url（原值 {$currentThumb}）\n";
                }
                continue;
            }
        }

        $newThumb = $site . '/thumbs/' . substr($sha1, 0, 2) . '/' . $targetName;
        if ($newThumb !== $currentThumb) {
            $update->execute([$newThumb, (int) $row['id']]);
            $fixed++;
            printf(
                "    #%s %s\n        旧: %s\n        新: %s\n",
                $row['id'],
                $currentName !== '' && $currentName !== $targetName ? '缩略图改名为 sha1 规则' : '修正站点地址',
                $currentThumb !== '' ? $currentThumb : '(空)',
                $newThumb
            );
        }
    }

    echo "  修正 {$fixed} 条，清空 {$cleared} 条\n\n";
    $totalFixed += $fixed;
    $totalCleared += $cleared;
}

echo "合计：修正 {$totalFixed} 条，清空 {$totalCleared} 条\n";
