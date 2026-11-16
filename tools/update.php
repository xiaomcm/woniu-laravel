<?php
/**
 * 蜗牛图床 · 一键更新脚本
 * 作者 QQ：34995549
 *
 * 用法（在有 app/ config/ public/ 的那一层站点目录执行）：
 *   php tools/update.php /tmp/woniu-laravel-v2.0.4.zip
 *   php tools/update.php /tmp/woniu-laravel-v2.0.4.zip --yes
 *
 * 为什么是「下载整包 + 脚本覆盖」，而不是「后台点一下在线更新」：
 *   1. 程序自己覆盖自己，下载 / 解压 / 写入任何一步断了，站点就起不来，而且没法在错误发生前回滚；
 *   2. 国内服务器连 GitHub 经常超时，在线拉取的成功率没保证；
 *   3. 在线更新得给 web 用户写代码目录的权限，一旦被入侵等于开了「远程写任意文件」的门；
 *   4. 每个旧版本 Release 都完整留着，手动覆盖随时能退回去。
 * 数据库不用手动导：程序每次请求前会自己把新字段补上（Migrator::run）。
 *
 * 这个脚本只做一件事：把新包的代码盖上去，同时死守数据与配置——
 *   .env、config/installed.php、storage/ 整个目录、bootstrap/cache 的运行时缓存，一个都不碰。
 * 覆盖前先把「会被改动的代码」打一份备份放进 storage/backup/，出问题能找回来。
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('请在命令行执行');
}

/** 项目根目录：本脚本在 tools/ 下，上一级就是站点根 */
$root = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');

/** 覆盖时绝不写入的清单（相对项目根，正斜杠） */
function shouldKeep(string $rel): bool
{
    if ($rel === '.env') {
        return true;
    }
    if ($rel === 'config/installed.php') {
        return true;
    }
    if ($rel === 'storage/install.lock') {
        return true;
    }
    // 整个 storage 都是运行时数据：数据库、图片、缩略图、日志、会话、缓存
    // 新包里 storage 只有 .gitignore 占位，跳过它不会漏掉任何代码
    if ($rel === 'storage' || str_starts_with($rel, 'storage/')) {
        return true;
    }
    // bootstrap/cache 是框架生成的缓存，交给 clearCaches 处理，这里不覆盖
    if ($rel === 'bootstrap/cache' || str_starts_with($rel, 'bootstrap/cache/')) {
        return true;
    }
    return false;
}

function fail(string $msg): never
{
    fwrite(STDERR, "更新失败：$msg\n");
    exit(1);
}

function step(string $msg): void
{
    echo "[更新] {$msg}\n";
}

function usage(): void
{
    echo "用法：php tools/update.php <新版本.zip> [--yes] [--force]\n";
    echo "  --yes    跳过确认，直接覆盖\n";
    echo "  --force  版本号不比当前新时也允许覆盖\n";
}

function versionFromCode(string $code): string
{
    if (preg_match("/'version'\s*=>\s*'([^']+)'/", $code, $m)) {
        return trim($m[1]);
    }
    return '';
}

/**
 * 认出包内的项目顶层目录。发布包固定是 woniu-laravel/，
 * 但用户可能自己改过目录名，所以兜底再扫一遍一层目录。
 */
function detectPrefix(ZipArchive $zip): string
{
    if ($zip->locateName('woniu-laravel/config/defaults.php') !== false) {
        return 'woniu-laravel/';
    }
    $n = $zip->numFiles;
    for ($i = 0; $i < $n; $i++) {
        $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
        if (preg_match('#^([^/]+/)?config/defaults\.php$#', $name, $m)) {
            return $m[1] ?? '';
        }
    }
    fail('包里找不到 config/defaults.php，这不是蜗牛图床的整包');
}

function validatePackage(ZipArchive $zip, string $prefix): void
{
    // 少任何一样都可能是下错文件，宁可停下也别把站点盖坏
    foreach (['public/index.php', 'app/', 'config/', 'vendor/autoload.php'] as $need) {
        $probe = $prefix . $need;
        $hit = $zip->locateName($probe) !== false
            || $zip->locateName(rtrim($probe, '/') . '/') !== false;
        if (!$hit) {
            fail("包里缺 {$need}，可能下错文件了");
        }
    }
}

/** 备份「将被覆盖的代码」，不含数据库和图片；返回 [路径, 文件数] */
function backupCode(string $root, string $oldVer): array
{
    $dir = $root . '/storage/backup';
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        fail("建不了备份目录：$dir");
    }
    $path = $dir . '/code-before-v' . $oldVer . '-' . date('Ymd-His') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fail("建不了备份包：$path");
    }

    $skipTree = ['.git', 'node_modules', 'dist', '.workbuddy', 'storage'];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $count = 0;
    foreach ($it as $item) {
        $p = str_replace('\\', '/', $item->getPathname());
        $rel = ltrim(substr($p, strlen($root)), '/');
        if ($rel === '') {
            continue;
        }
        foreach ($skipTree as $t) {
            if ($rel === $t || str_starts_with($rel, $t . '/')) {
                continue 2;
            }
        }
        if ($item->isDir()) {
            $zip->addEmptyDir($rel);
            continue;
        }
        $zip->addFile($item->getPathname(), $rel);
        $count++;
    }
    $zip->close();
    return [$path, $count];
}

/** 清掉视图 / 服务缓存，否则改了 blade 和加了 provider 都不生效 */
function clearCaches(string $root): int
{
    $n = 0;
    $targets = [
        $root . '/bootstrap/cache',
        $root . '/storage/framework/views',
        $root . '/storage/framework/cache/data',
    ];
    foreach ($targets as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        foreach (glob($dir . '/*.php') as $f) {
            if (@unlink($f)) {
                $n++;
            }
        }
    }
    return $n;
}

// ---------- 参数 ----------
$args = array_slice($argv, 1);
$yes = false;
$force = false;
$zipPath = null;
foreach ($args as $a) {
    if ($a === '--yes' || $a === '-y') { $yes = true; continue; }
    if ($a === '--force') { $force = true; continue; }
    if ($a === '--help' || $a === '-h') { usage(); exit(0); }
    if ($zipPath === null && !str_starts_with($a, '-')) { $zipPath = $a; continue; }
    fail("不认识的参数：$a");
}
if ($zipPath === null) {
    usage();
    fail('缺少新版本 zip 路径');
}

if (!class_exists('ZipArchive')) {
    fail('PHP 没开 zip 扩展，服务器装一下 php-zip');
}
$zipPathReal = realpath($zipPath);
if ($zipPathReal === false || !is_file($zipPathReal)) {
    fail("找不到 zip：$zipPath");
}

// ---------- 读新旧版本 ----------
$currentCode = @file_get_contents($root . '/config/defaults.php');
if ($currentCode === false) {
    fail("读不到 {$root}/config/defaults.php，当前目录不对");
}
$oldVer = versionFromCode($currentCode);
if ($oldVer === '') {
    fail('当前 config/defaults.php 里没有版本号');
}

$zip = new ZipArchive();
if ($zip->open($zipPathReal) !== true) {
    fail("打不开 zip：$zipPathReal");
}
$prefix = detectPrefix($zip);
validatePackage($zip, $prefix);

$newCode = $zip->getFromName($prefix . 'config/defaults.php');
if ($newCode === false) {
    fail('包里读不到 config/defaults.php');
}
$newVer = versionFromCode($newCode);
if ($newVer === '') {
    fail('包里的 config/defaults.php 没有版本号');
}

step("当前版本 v{$oldVer} → 新版本 v{$newVer}");

if (!$force && version_compare($newVer, $oldVer, '<=')) {
    fail("新版本不比当前新（{$newVer} <= {$oldVer}），确认要覆盖就加 --force");
}

if (!$yes) {
    echo "会把新代码覆盖到 $root\n";
    echo "你的数据库、图片、配置（.env / installed.php）不会被碰。继续？[y/N] ";
    $line = fgets(STDIN);
    if ($line === false || !in_array(strtolower(trim($line)), ['y', 'yes'], true)) {
        echo "已取消，什么都没改。\n";
        $zip->close();
        exit(0);
    }
}

// ---------- 备份 ----------
step('备份当前代码（不含数据库和图片）…');
[$bakPath, $bakCount] = backupCode($root, $oldVer);
step("备份好了：{$bakPath}（{$bakCount} 个文件）");

// ---------- 覆盖 ----------
$written = 0;
$kept = 0;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
    if (!str_starts_with($name, $prefix)) {
        continue;
    }
    $rel = substr($name, strlen($prefix));
    if ($rel === '' || str_ends_with($rel, '/')) {
        continue;
    }
    if (shouldKeep($rel)) {
        $kept++;
        continue;
    }
    $data = $zip->getFromIndex($i);
    if ($data === false) {
        fail("读不出包内文件：$rel");
    }
    $dest = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $parent = dirname($dest);
    if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
        fail("建不了目录：$parent");
    }
    if (file_put_contents($dest, $data) === false) {
        fail("写不进去：$rel（检查目录属主和权限）");
    }
    $written++;
}
$zip->close();
step("覆盖完成：{$written} 个代码文件，跳过 {$kept} 个数据 / 配置文件");

// ---------- 清缓存 ----------
$cleared = clearCaches($root);
step("清掉 {$cleared} 个旧缓存文件");

// ---------- 收尾 ----------
echo "\n更新完成：v{$oldVer} → v{$newVer}\n";
echo "数据库字段会在第一次访问网站时自动补齐，不用手动导 SQL。\n";
echo "如果发现新版有问题：\n";
echo "  1. 用 storage/backup/ 里那份备份把代码盖回去；\n";
echo "  2. 或者去 GitHub Releases 下载旧版本整包，重新覆盖一次。\n";
echo "回退前先把当前的数据库和 storage/ 另存一份，别省这一步。\n";
