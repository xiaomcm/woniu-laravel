<?php
/**
 * 一键推送 WONIU 到 GitHub（Windows / Git Bash / Linux 通用）
 *
 * 流程：用 Token 调 GitHub API 确认或创建仓库 → 设置 remote → 推送 main 分支。
 *
 * Token 只通过环境变量或命令行参数传入，**不写进 .git/config**：
 * 推送时用 `git -c http.extraHeader=...` 临时带上认证头，仓库地址保持干净。
 *
 * 用法：
 *   GH_TOKEN=ghp_xxx php tools/push_github.php --repo=woniu-laravel
 *   php tools/push_github.php --token=ghp_xxx --repo=woniu-laravel --private
 *   php tools/push_github.php --token=ghp_xxx --repo=woniu-laravel --owner=someone
 *
 * 参数：
 *   --token=xxx     GitHub Personal Access Token（也可用环境变量 GH_TOKEN）
 *   --repo=name     仓库名，默认 woniu-laravel
 *   --owner=xxx     仓库归属账号，默认用 Token 对应的账号
 *   --private       建为私有仓库（默认公开）
 *   --desc=xxx      仓库描述
 *   --no-create     只推送到已存在的仓库，不新建
 *   --dry-run       只打印要做什么，不实际推送
 */

declare(strict_types=1);

$root = dirname(__DIR__);

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

function hasFlag(string $name): bool
{
    global $argv;
    return in_array('--' . $name, $argv, true);
}

$token = argOf('token') ?: (getenv('GH_TOKEN') ?: '');
$repo = argOf('repo', 'woniu-laravel');
$owner = argOf('owner');
$private = hasFlag('private');
$noCreate = hasFlag('no-create');
$dryRun = hasFlag('dry-run');
$desc = argOf('desc', '蜗牛图床 WONIU —— 基于 Laravel 13 的图片托管系统');

if ($token === '') {
    fwrite(STDERR, "缺少 Token。用 --token=xxx 传入，或设环境变量 GH_TOKEN\n");
    exit(1);
}

// ── 1. 验证 Token 并取用户名 ───────────────────────────────
/**
 * 调 GitHub API。
 *
 * 用系统 curl 而不是 PHP 的 curl 扩展：Windows 上的 PHP CLI 不带 CA bundle
 * （PHP 的 openssl 不读 Windows 证书库），直接调 https 会报
 * "SSL certificate OpenSSL verify result: unable to get local issuer certificate"。
 * 系统 curl.exe 用的是 Windows 证书库，TLS 校验正常。
 */
function curlBinary(): string
{
    // 先扫 PATH 里的每个目录（Windows 下 PATH_SEPARATOR 是 ';'）
    $path = (string) getenv('PATH');
    foreach (explode(PATH_SEPARATOR, $path) as $dir) {
        $dir = trim($dir, " \t\n\r\0\x0B\"");
        if ($dir === '') {
            continue;
        }
        foreach (['curl.exe', 'curl'] as $bin) {
            $full = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $bin;
            if (is_file($full)) {
                return $full;
            }
        }
    }

    // 再试几个常见安装位置
    foreach ([
        'C:\\Windows\\System32\\curl.exe',
        'C:\\Program Files\\Git\\mingw64\\bin\\curl.exe',
        'C:\\Program Files\\Git\\usr\\bin\\curl.exe',
        '/usr/bin/curl',
        '/usr/local/bin/curl',
    ] as $bin) {
        if (is_file($bin)) {
            return $bin;
        }
    }

    fwrite(STDERR, "找不到 curl 命令，无法调用 GitHub API。\n");
    exit(1);
}

function gh(string $method, string $url, string $token, ?array $body = null): array
{
    $bin = curlBinary();

    $cmd = sprintf(
        '%s -s -X %s -H %s -H %s -H %s -H %s -w "\n%%{http_code}"',
        $bin,
        $method,
        escapeshellarg('Authorization: Bearer ' . $token),
        escapeshellarg('Accept: application/vnd.github+json'),
        escapeshellarg('X-GitHub-Api-Version: 2022-11-28'),
        escapeshellarg('User-Agent: woniu-push-script')
    );

    $tmpFile = null;
    if ($body !== null) {
        // JSON 走临时文件，不内联进命令行。
        // Windows 的 exec() 走 cmd.exe，引号解析规则与 POSIX shell 不同：
        // escapeshellarg() 会把 JSON 里的双引号改写掉，传到 curl 时已不是合法 JSON，
        // GitHub 直接返回 400 "Problems parsing JSON"。中文描述的 UTF-8 也会在命令行里被截断。
        $tmpFile = (string) tempnam(sys_get_temp_dir(), 'gh_body_');
        file_put_contents($tmpFile, (string) json_encode($body, JSON_UNESCAPED_UNICODE));
        $cmd .= ' -H ' . escapeshellarg('Content-Type: application/json');
        $cmd .= ' --data ' . escapeshellarg('@' . $tmpFile);
    }

    $cmd .= ' ' . escapeshellarg($url);

    $out = [];
    $code = 0;
    exec($cmd, $out, $code);

    if ($tmpFile !== null) {
        @unlink($tmpFile);
    }

    $lines = $out;
    $httpCode = 0;
    if (!empty($lines) && preg_match('/^\d{3}$/', (string) end($lines))) {
        $httpCode = (int) array_pop($lines);
    }
    $raw = implode("\n", $lines);
    $data = json_decode($raw, true);

    return [
        'code' => $httpCode,
        'data' => is_array($data) ? $data : [],
        'error' => ($httpCode === 0 || $httpCode >= 400) ? $raw : null,
    ];
}

$me = gh('GET', 'https://api.github.com/user', $token);
if ($me['code'] !== 200) {
    $msg = $me['error'] ?: ($me['data']['message'] ?? '未知错误');
    fwrite(STDERR, "Token 验证失败（HTTP {$me['code']}）：{$msg}\n");
    if ($me['code'] === 401) {
        fwrite(STDERR, "提示：Token 已过期、复制不全、或勾错了权限（需要 repo）。\n");
    }
    exit(1);
}

$login = $owner ?: (string) ($me['data']['login'] ?? '');
echo "认证成功：{$login}\n";
$fullRepo = "{$login}/{$repo}";

// ── 2. 确认仓库存在，不存在就建 ───────────────────────────
$probe = gh('GET', "https://api.github.com/repos/{$fullRepo}", $token);
$exists = ($probe['code'] === 200);

if (!$exists) {
    if ($noCreate) {
        fwrite(STDERR, "仓库 {$fullRepo} 不存在，且指定了 --no-create。\n");
        exit(1);
    }
    if ($probe['code'] !== 404) {
        fwrite(STDERR, "查询仓库失败（HTTP {$probe['code']}）：" . ($probe['error'] ?: '') . "\n");
        exit(1);
    }

    if ($dryRun) {
        echo "[dry-run] 将创建仓库 {$fullRepo}（" . ($private ? '私有' : '公开') . "）\n";
        $exists = true;
    } else {
        echo "创建仓库 {$fullRepo}（" . ($private ? '私有' : '公开') . "）...\n";
        $create = gh('POST', 'https://api.github.com/user/repos', $token, [
            'name'        => $repo,
            'description' => $desc,
            'private'     => $private,
            'auto_init'   => false,
            'has_issues'  => true,
            'has_wiki'    => false,
        ]);
        if ($create['code'] !== 201) {
            $msg = $create['error'] ?: ($create['data']['message'] ?? '未知错误');
            fwrite(STDERR, "创建仓库失败（HTTP {$create['code']}）：{$msg}\n");
            exit(1);
        }
        echo "仓库已创建：https://github.com/{$fullRepo}\n";
        $exists = true;
    }
} else {
    echo "仓库已存在：https://github.com/{$fullRepo}\n";
}

// ── 3. 设置 remote ───────────────────────────────────────
$remoteUrl = "https://github.com/{$fullRepo}.git";

/**
 * 在项目根目录执行一条 git 命令。
 *
 * 不写 `2>/dev/null` —— Windows 上 exec() 走 cmd.exe，不认 /dev/null，
 * 会把它当路径去打开并报 "The system cannot find the path specified."，
 * 顺带把真正的 stderr 和正常输出混在一起。统一用 2>&1 收进 $out。
 */
function gitIn(string $root, string $args): array
{
    $out = [];
    $code = 0;
    exec('git -C ' . escapeshellarg($root) . ' ' . $args . ' 2>&1', $out, $code);

    return [$code, trim(implode("\n", $out))];
}

[, $current] = gitIn($root, 'remote get-url origin');
if ($current === $remoteUrl) {
    echo "remote origin 已是 {$remoteUrl}\n";
} elseif ($current !== '') {
    gitIn($root, 'remote set-url origin ' . escapeshellarg($remoteUrl));
    echo "remote origin 已更新为 {$remoteUrl}\n";
} else {
    [$addCode] = gitIn($root, 'remote add origin ' . escapeshellarg($remoteUrl));
    if ($addCode === 0) {
        echo "remote origin 已添加：{$remoteUrl}\n";
    } else {
        // 同名 remote 已存在（add 报 "remote origin already exists"），改地址即可
        gitIn($root, 'remote set-url origin ' . escapeshellarg($remoteUrl));
        echo "remote origin 已更新为 {$remoteUrl}\n";
    }
}

// ── 4. 推送 ───────────────────────────────────────────────
if ($dryRun) {
    echo "[dry-run] 将执行：git push -u origin main\n";
    exit(0);
}

$branch = 'main';
[$branchCode, $branchOut] = gitIn($root, 'rev-parse --abbrev-ref HEAD');
if ($branchCode === 0 && $branchOut !== '') {
    $branch = $branchOut;
}

$auth = base64_encode('x-access-token:' . $token);
$cmd = sprintf(
    'git -C %s -c http.extraHeader="Authorization: Basic %s" push -u origin %s 2>&1',
    escapeshellarg($root),
    $auth,
    escapeshellarg($branch)
);

echo "推送分支 {$branch} ...\n";
$out = [];
$code = 0;
exec($cmd, $out, $code);
foreach ($out as $line) {
    echo '  ' . $line . "\n";
}

if ($code !== 0) {
    fwrite(STDERR, "推送失败（退出码 {$code}）\n");
    exit($code);
}

echo "\n推送完成：https://github.com/{$fullRepo}\n";
echo "克隆地址：git clone {$remoteUrl}\n";
