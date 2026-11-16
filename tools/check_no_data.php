<?php
/**
 * 检查版本库里有没有混进测试数据 / 运行数据。
 *
 * 用法：
 *   php tools/check_no_data.php                 # 只查 git 跟踪的文件
 *   php tools/check_no_data.php --zip=dist/x.zip # 顺带查打包产物内部
 *
 * 判定为「不该入库」的东西：
 *   · storage/ 或 public/ 下的图片（用户上传的图、缩略图）
 *   · 任何 .sqlite / .sqlite3 / .db 库文件
 *   · .env、config/installed.php
 *   · storage/logs 下的日志、任意 .log
 *   · 安装锁文件
 *
 * 程序自己用的图标不算：public/assets/ 下的 logo、favicon 是源码资源，必须留着。
 *
 * 退出码 0 = 干净，1 = 有东西混进来了（CI / 发版脚本据此中断）。
 */

$root = dirname(__DIR__);

$arg = static function (string $k): ?string {
    foreach ($GLOBALS['argv'] as $a) {
        if (str_starts_with($a, '--' . $k . '=')) {
            return substr($a, strlen($k) + 3);
        }
    }
    return null;
};

/** 该路径属于「不允许进版本库」的运行时数据吗 */
function isForbidden(string $path): ?string
{
    $p = str_replace('\\', '/', ltrim($path, '/'));

    // .gitignore 是目录占位，本身不是数据
    if (basename($p) === '.gitignore') {
        return null;
    }

    // 程序自己的图标资源，放行
    if (str_starts_with($p, 'public/assets/') || $p === 'public/favicon.ico') {
        return null;
    }
    if (preg_match('#^resources/#', $p)) {
        return null;
    }

    if ($p === '.env' || str_ends_with($p, '/.env')) {
        return '.env 里有本机密钥，不能入库';
    }
    if ($p === 'config/installed.php') {
        return 'config/installed.php 里有数据库密码和 APP_KEY';
    }
    if (preg_match('#\.(sqlite|sqlite3|db)$#i', $p) || preg_match('#\.sqlite\.#i', $p)) {
        return '数据库文件';
    }
    if (preg_match('#^storage/(uploads|thumbs)/.+\.(jpg|jpeg|png|gif|webp|bmp|svg)$#i', $p)) {
        return '用户图片 / 缩略图';
    }
    if (preg_match('#^public/(uploads|thumbs)/#', $p)) {
        return 'public 下的上传目录';
    }
    if (str_starts_with($p, 'storage/logs/') && !str_ends_with($p, '/')) {
        return '日志文件';
    }
    if (str_ends_with($p, '.log')) {
        return '日志文件';
    }
    if ($p === 'storage/install.lock') {
        return '安装锁';
    }
    if (str_ends_with($p, '.phpunit.result.cache')) {
        return '测试缓存';
    }

    return null;
}

$problems = [];

// ——— 1) git 跟踪的文件 ———
$out = [];
$code = 0;
exec('git -C ' . escapeshellarg($root) . ' ls-files 2>&1', $out, $code);
if ($code !== 0) {
    fwrite(STDERR, "读不到 git 文件列表，确认这是不是一个 git 仓库\n");
    exit(1);
}
foreach ($out as $f) {
    $why = isForbidden($f);
    if ($why !== null) {
        $problems[] = "[版本库] {$f}  ← {$why}";
    }
}
$tracked = count($out);

// ——— 2) 打包产物（可选）———
$zipPath = $arg('zip');
$zipEntries = 0;
if ($zipPath !== null) {
    if (!is_file($zipPath)) {
        fwrite(STDERR, "压缩包不存在：{$zipPath}\n");
        exit(1);
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        fwrite(STDERR, "打不开压缩包：{$zipPath}\n");
        exit(1);
    }
    $zipEntries = $zip->numFiles;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $n = $zip->getNameIndex($i);
        // 去掉包内顶层目录 woniu-laravel/
        $rel = preg_replace('#^woniu-laravel/#', '', str_replace('\\', '/', $n));
        $why = isForbidden($rel);
        if ($why !== null) {
            $problems[] = "[包内] {$n}  ← {$why}";
        }
    }
    $zip->close();
}

// ——— 输出 ———
echo "=== 测试数据检查 ===\n";
echo " 版本库文件数 : {$tracked}\n";
if ($zipPath !== null) {
    echo " 包内条目数   : {$zipEntries}（{$zipPath}）\n";
}

if ($problems === []) {
    echo "\n结果: 干净，没有测试数据\n";
    exit(0);
}

echo "\n结果: 发现 " . count($problems) . " 处问题\n\n";
foreach (array_slice($problems, 0, 30) as $p) {
    echo "  {$p}\n";
}
if (count($problems) > 30) {
    echo '  ...还有 ' . (count($problems) - 30) . " 处\n";
}
echo "\n处理办法：从版本库移除但保留本地文件 ——\n";
echo "  git rm --cached <路径>\n";
echo "然后确认 .gitignore 里有对应规则。\n";
exit(1);
