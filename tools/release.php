<?php
/**
 * 蜗牛图床 WONIU — 一键发版
 *
 * 用法：
 *   GH_TOKEN=ghp_xxx php tools/release.php --version=2.0.1 --notes="修了几个小问题"
 *   php tools/release.php --version=2.0.1 --dry-run         只走流程不落地
 *   php tools/release.php --version=2.0.1 --skip-tests       跳过全量测试
 *
 * 干的事，按顺序：
 *   1. 校验版本号（格式 + 必须是「上一版 +1」，2.0.9 之后跳 3.0.0）
 *   2. 改 config/defaults.php 的 version
 *   3. 在 CHANGELOG.md 顶部插一节新版本
 *   4. 跑全量 PHPUnit，挂了就停
 *   5. 打整包 dist/woniu-laravel-v{ver}.zip（排除运行时数据）
 *   6. git commit + push
 *   7. 新建 Release 并传附件
 *
 * **绝不删旧 Release。** 历史版本全部留着，谁想回退随时能下。
 *
 * Token 只从环境变量 GH_TOKEN 读，不写进任何文件。中文说明走临时文件，
 * 不经过命令行（Windows 下引号会把 JSON 弄坏）。
 */
declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

function argOf(string $name, ?string $default = null): ?string
{
    global $argv;
    foreach ($argv as $a) {
        if (str_starts_with($a, "--{$name}=")) {
            return substr($a, strlen($name) + 3);
        }
    }
    return $default;
}

function flag(string $name): bool
{
    global $argv;
    return in_array("--{$name}", $argv, true);
}

function fail(string $msg): never
{
    fwrite(STDERR, "✗ {$msg}\n");
    exit(1);
}

function ok(string $msg): void
{
    echo "✓ {$msg}\n";
}

function gitIn(string $root, string $args): array
{
    $out = [];
    $code = 0;
    // Windows 的 exec 走 cmd.exe，不认 /dev/null，统一 2>&1
    exec('git -C ' . escapeshellarg($root) . ' ' . $args . ' 2>&1', $out, $code);
    return [$code, trim(implode("\n", $out))];
}

/**
 * 调 GitHub API。返回 [httpCode, decodedArray, rawBody]。
 */
function api(string $method, string $url, ?array $payload = null, ?string $token = null, ?string $uploadFile = null): array
{
    $cmd = 'curl -s -w "\n%{http_code}" -X ' . $method;
    $tmp = null;

    if ($token !== null) {
        $cmd .= ' -H ' . escapeshellarg('Authorization: Bearer ' . $token);
    }
    $cmd .= ' -H ' . escapeshellarg('Accept: application/vnd.github+json');

    if ($payload !== null) {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'gh_body_');
        file_put_contents($tmp, (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $cmd .= ' -H ' . escapeshellarg('Content-Type: application/json');
        $cmd .= ' --data-binary ' . escapeshellarg('@' . $tmp);
    }

    if ($uploadFile !== null) {
        $cmd .= ' -H ' . escapeshellarg('Content-Type: application/zip');
        $cmd .= ' --data-binary ' . escapeshellarg('@' . $uploadFile);
    }

    $cmd .= ' ' . escapeshellarg($url);

    $out = [];
    $code = 0;
    exec($cmd, $out, $code);

    if ($tmp !== null) {
        @unlink($tmp);
    }

    $httpCode = 0;
    if ($out !== [] && preg_match('/^\d{3}$/', (string) end($out))) {
        $httpCode = (int) array_pop($out);
    }
    $raw = implode("\n", $out);

    return [$httpCode, json_decode($raw, true), $raw];
}

/**
 * 版本号递增规则：a.b.c → a.b.(c+1)；c 到 9 之后跳 (a+1).0.0。
 */
function nextVersion(string $cur): string
{
    if (!preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $cur, $m)) {
        fail("当前版本号格式不对: {$cur}");
    }
    [, $a, $b, $c] = array_map('intval', $m);

    if ($c < 9) {
        return "{$a}.{$b}." . ($c + 1);
    }
    return ($a + 1) . '.0.0';
}

// ────────────────────────────── 参数 ──────────────────────────────
$version = (string) (argOf('version') ?? '');
if ($version === '') {
    fail("缺 --version=2.0.1");
}
if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    fail("版本号要写成 x.y.z，例如 2.0.1");
}

$dryRun   = flag('dry-run');
$skipTest = flag('skip-tests');
$resume   = flag('resume');   // 发版中途断了（比如推送失败）时，从推送这步接着做
$notes    = (string) (argOf('notes') ?? '');
// 中文说明走文件传，避免 Windows 命令行把 UTF-8 变成乱码
$notesFile = argOf('notes-file');
if ($notesFile !== null) {
    $content = @file_get_contents($notesFile);
    if ($content === false) {
        fail("读不到说明文件: {$notesFile}");
    }
    $notes = trim($content);
}
$token    = (string) (getenv('GH_TOKEN') ?: '');
$assetName = "woniu-laravel-v{$version}.zip";

// ────────────────────────── 校验版本递增 ──────────────────────────
$defaultsFile = $root . '/config/defaults.php';
if (!is_file($defaultsFile)) {
    fail("找不到 config/defaults.php");
}
$defaults = (string) file_get_contents($defaultsFile);
if (!preg_match("/'version'\s*=>\s*'([^']+)'/", $defaults, $m)) {
    fail("config/defaults.php 里没有 'version' 定义");
}
$current = $m[1];
$expected = nextVersion($current);

if ($resume) {
    // 续发：版本号已经在上一轮改过了，只要求目标号跟当前一致
    if ($version !== $current) {
        fail("续发模式下版本号要跟当前一致（当前 {$current}），不能是 {$version}");
    }
    ok("续发模式：版本号已是 {$version}，跳过改号与提交");
} else {
    if ($version !== $expected) {
        fail("版本号只能往上一格走：当前 {$current}，下一个应该是 {$expected}（2.0.9 之后跳 3.0.0）");
    }
    ok("版本号 {$current} → {$version}");
}

// 工作区必须干净，避免把半截改动打进包
[$code, $status] = gitIn($root, 'status --porcelain');
if ($status !== '') {
    fail("工作区不干净，先提交或撤销：\n{$status}");
}
ok("工作区干净");

[$code, $branch] = gitIn($root, 'rev-parse --abbrev-ref HEAD');
if (trim($branch) !== 'main') {
    fail("当前分支是 {$branch}，发版要在 main 上");
}

// tag 不能已存在
[$code, $tagOut] = gitIn($root, 'tag -l v' . $version);
if (trim($tagOut) !== '') {
    fail("tag v{$version} 已存在，换个版本号或先删 tag");
}

if ($dryRun) {
    echo "\n—— dry-run，以下动作不会真的执行 ——\n";
}
if (!$dryRun && $token === '') {
    fail("缺 GH_TOKEN 环境变量（GitHub 个人令牌）");
}

// ──────────────────────── 1. 改版本号 ────────────────────────
if (!$resume) {
    $newDefaults = preg_replace("/'version'\s*=>\s*'[^']+'/", "'version'   => '{$version}'", $defaults, 1);
    if ($newDefaults === null) {
        fail("替换版本号失败");
    }
    if (!$dryRun) {
        file_put_contents($defaultsFile, $newDefaults);
    }
    ok("config/defaults.php 版本号改为 {$version}");
}

// ──────────────────────── 2. 写 CHANGELOG ────────────────────────
$changelogFile = $root . '/CHANGELOG.md';
$changelog = is_file($changelogFile) ? (string) file_get_contents($changelogFile) : "# 更新记录\n";

$today = date('Y-m-d');
$noteLines = $notes !== ''
    ? implode("\n", array_map(static fn ($l) => '- ' . ltrim($l, '- '), preg_split('/\r?\n/', $notes)))
    : '- 这一版的具体改动（发版时补上）';

$section = "## v{$version} · {$today}\n\n{$noteLines}\n\n";

// 插到 "# 更新记录" 的说明段之后、第一个 "## v" 之前
$pos = strpos($changelog, "\n## v");
if ($pos === false) {
    $newChangelog = rtrim($changelog) . "\n\n" . $section;
} else {
    $newChangelog = substr($changelog, 0, $pos + 1) . $section . substr($changelog, $pos + 1);
}
if (!$dryRun && !$resume) {
    file_put_contents($changelogFile, $newChangelog);
}
ok($resume ? "CHANGELOG.md 已有 v{$version} 一节" : "CHANGELOG.md 加了 v{$version} 一节");

// ──────────────────────── 3. 跑测试 ────────────────────────
$php = PHP_BINARY;
if ($skipTest) {
    echo "· 跳过测试（--skip-tests）\n";
} elseif ($dryRun) {
    echo "· dry-run 跳过测试\n";
} else {
    echo "· 跑全量测试…\n";
    $out = [];
    $code = 0;
    exec(escapeshellarg($php) . ' ' . escapeshellarg($root . '/vendor/bin/phpunit') . ' 2>&1', $out, $code);
    $tail = implode("\n", array_slice($out, -6));
    if ($code !== 0) {
        fwrite(STDERR, $tail . "\n");
        fail("测试没过，发版中止");
    }
    $okLines = preg_grep('/^OK \(/', $out);
    if ($okLines !== []) {
        echo "  " . trim((string) end($okLines)) . "\n";
    }
    ok("测试通过");
}

// ──────────────────────── 4. 打整包 ────────────────────────
$distDir = $root . '/dist';
if (!is_dir($distDir) && !$dryRun) {
    mkdir($distDir, 0777, true);
}
$zipPath = $distDir . '/' . $assetName;

// tests/ 与测试配置属于本地开发文件，绝不能进用户安装包——
// 用户要的是「解压即装」的干净程序，不是带着一堆契约测试的开发副本。
$skipTree  = ['.git', 'node_modules', '.workbuddy', 'dist', 'tests',
              'database/factories', 'database/seeders'];
$skipFiles = [
    '.env', 'config/installed.php', 'storage/install.lock', '.phpunit.result.cache',
    'phpunit.xml', 'phpunit.xml.dist', 'package.json', 'package-lock.json', '.phpunit.cache',
    // 本地开发测试脚本（服务器上已清掉，发布的干净包也不该带）
    '.htaccess', '404.html',
    'tools/local_server.sh',
    'tools/block_gap_test.cjs', 'tools/card_gap_test.cjs', 'tools/footer_check.cjs',
    'tools/landing_overlap_test.cjs', 'tools/lightbox_click_test.cjs', 'tools/lightbox_test.cjs',
    'tools/local_smoke.cjs', 'tools/op_btn_test.cjs', 'tools/settings_gap_test.cjs',
    'tools/shot.cjs', 'tools/user_menu_center_test.cjs', 'tools/user_menu_width_test.cjs',
    'tools/verify_account_label.php', 'tools/verify_admin_title.php',
    'tools/verify_homepage_isolation.php', 'tools/verify_lazy_load.php',
    'tools/verify_package_duration.php', 'tools/verify_purchase_limit.php',
    'tools/probe_home_normal.php', 'tools/probe_home_state.php',
    'database/factories/UserFactory.php', 'database/seeders/DatabaseSeeder.php',
];
$onlyGitignore = ['storage', 'bootstrap/cache'];

if (!$dryRun) {
    @unlink($zipPath);
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fail("创建 zip 失败: {$zipPath}");
    }
    $count = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $path = str_replace('\\', '/', $item->getPathname());
        $rel = ltrim(substr($path, strlen(str_replace('\\', '/', $root))), '/');
        if ($rel === '') {
            continue;
        }
        foreach ($skipTree as $t) {
            if ($rel === $t || str_starts_with($rel, $t . '/')) {
                continue 2;
            }
        }
        $inZip = 'woniu-laravel/' . $rel;
        if ($item->isDir()) {
            $zip->addEmptyDir($inZip);
            continue;
        }
        if (in_array($rel, $skipFiles, true)) {
            continue;
        }
        foreach ($onlyGitignore as $dir) {
            if (($rel === $dir || str_starts_with($rel, $dir . '/')) && basename($rel) !== '.gitignore') {
                continue 2;
            }
        }
        if (preg_match('#^storage/[^/]*\.sqlite(\.bak.*)?$#', $rel)) {
            continue;
        }
        $zip->addFile($item->getPathname(), $inZip);
        $count++;
    }
    $zip->close();

    // 兜底：包里不能有本地配置或开发/测试文件（双保险，防止以后改打包规则时漏掉）
    $check = new ZipArchive();
    $check->open($zipPath);
    for ($i = 0; $i < $check->numFiles; $i++) {
        $n = $check->getNameIndex($i);
        // 注意锚定：vendor/ 里框架自带 package.json 是正常的，只拦项目根目录那几个
        if (preg_match('#^woniu-laravel/(\.env$|config/installed\.php|storage/install\.lock|storage/[^/]*\.sqlite|tests/|database/(factories|seeders)/|phpunit\.xml(\.dist)?$|package(-lock)?\.json$|\.phpunit\.cache$|tools/.*(test\.cjs|local_server\.sh|probe_home_|verify_))#', $n)) {
            $check->close();
            @unlink($zipPath);
            fail("包里混进了本地/测试文件: {$n}");
        }
    }
    $check->close();
    ok("整包 " . $count . " 个文件, " . round(filesize($zipPath) / 1048576, 2) . " MB");
} else {
    echo "· dry-run 跳过打包（会生成 dist/{$assetName}）\n";
}

// ──────────────────────── 5. 提交 + 推送 ────────────────────────
if ($dryRun) {
    echo "\n—— 以下是 dry-run 计划 ——\n";
    echo "1. git add config/defaults.php CHANGELOG.md && git commit -m 'release: v{$version}'\n";
    echo "2. git push origin main\n";
    echo "3. POST /releases  tag=v{$version}  name=v{$version}\n";
    echo "4. POST uploads.github.com …?name={$assetName}\n";
    echo "\n没有动任何东西。\n";
    exit(0);
}

if (!$resume) {
    [$code, $out] = gitIn($root, 'add config/defaults.php CHANGELOG.md');
    if ($code !== 0) {
        fail("git add 失败: {$out}");
    }
    [$code, $out] = gitIn($root, 'commit -m ' . escapeshellarg("release: v{$version}"));
    if ($code !== 0) {
        fail("git commit 失败: {$out}");
    }
    ok("已提交");
} else {
    ok("跳过提交（上一轮已提交 release: v{$version}）");
}

// 推送要带上 token 认证头：这台机器没有缓存 git 凭据，普通的 `git push`
// 会弹登录框（无头环境直接失败 "could not read Username"）。仓库地址保持干净，
// 认证只在这一条命令里临时带，不写进 .git/config。
$auth = base64_encode('x-access-token:' . $token);
$pushCmd = sprintf(
    '-c http.extraHeader="Authorization: Basic %s" push origin %s',
    $auth,
    escapeshellarg($branch)
);
[$code, $out] = gitIn($root, $pushCmd);
if ($code !== 0) {
    fail("推送失败: {$out}");
}
ok("已推送到 main");

// ──────────────────────── 6. 建 Release ────────────────────────
$repo = 'xiaomcm/woniu-laravel';
if (preg_match('#github\.com[/:]([^/]+/[^/.]+)#', (string) gitIn($root, 'remote get-url origin')[1], $mm)) {
    $repo = $mm[1];
}

$body = buildReleaseBody($version, $notes, $root);

[$http, $data, $raw] = api('POST', "https://api.github.com/repos/{$repo}/releases", [
    'tag_name'         => 'v' . $version,
    'target_commitish' => 'main',
    'name'             => 'v' . $version,
    'body'             => $body,
    'draft'            => false,
    'prerelease'       => false,
], $token);

if ($http !== 201 || !isset($data['id'])) {
    fail("建 Release 失败 HTTP {$http}: " . substr($raw, 0, 300));
}
$releaseId = (int) $data['id'];
ok("Release v{$version} 建好（id={$releaseId}）");

// ──────────────────────── 7. 传附件 ────────────────────────
[$http, $up, $raw] = api(
    'POST',
    "https://uploads.github.com/repos/{$repo}/releases/{$releaseId}/assets?name=" . rawurlencode($assetName),
    null,
    $token,
    $zipPath
);
if ($http !== 201 || !isset($up['id'])) {
    fail("传附件失败 HTTP {$http}: " . substr($raw, 0, 300));
}
ok("附件已上传: " . number_format((int) $up['size']) . " 字节");

echo "\n发版完成：https://github.com/{$repo}/releases/tag/v{$version}\n";
echo "旧版本一个没动。\n";

/**
 * 拼 Release 说明：更新摘要 + 固定的安装/环境/版权/数据安全段落。
 */
function buildReleaseBody(string $version, string $notes, string $root): string
{
    $summary = '';
    if ($notes !== '') {
        $summary = "## 这个版本改了什么\n\n" .
            implode("\n", array_map(
                static fn ($l) => '- ' . ltrim(trim($l), '- '),
                array_filter(preg_split('/\r?\n/', $notes), static fn ($l) => trim($l) !== '')
            )) . "\n\n";
    }

    // 安装与数据安全段落固定复用，避免每次重写走样
    $tail = <<<'TXT'
## 怎么装

下载下面的 zip，解压到站点目录，把网站根目录指到 `public/`，浏览器打开就会自动进安装向导。包里已经带了 `vendor/`，不用再跑 composer，也不用装 Git。

用宝塔面板的话翻 INSTALL.md 第 3.5 节，13 步按顺序写好了。

## 环境

PHP 8.3 以上，MySQL 或 SQLite 都行，再带上 pdo、gd、mbstring、fileinfo、curl、openssl 这几个常见扩展。

## 升级

从旧版升上来的，第一次访问会自动补字段和设置项，不用手动改表。动手之前先备份 `storage/`、数据库和 `config/installed.php`。

官网：https://www.34995549.xyz
主机镇：https://www.zhujitown.com
版权归作者所有（QQ 34995549），可以自用、修改和商用；转载和修改请保留署名与官网地址。

## 数据和安全

图片、数据库、账号都在你自己的服务器上，作者这边没有副本，也不会帮忙恢复。装完记得定期备份；代码是公开的，正式对外用之前自己改掉默认密码、开 HTTPS、及时更新。完整说明见包里 README 的「数据声明」。

完整更新记录见仓库的 `CHANGELOG.md`。
TXT;

    return $summary . $tail;
}
