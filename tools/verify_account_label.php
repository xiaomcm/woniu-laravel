<?php
/**
 * 用户下拉菜单头部副标题（账号绑定邮箱）端到端验证。
 *
 * 流程：
 *   1. 查主库 users 表，确认没有「email 列存的是用户名」这种脏数据
 *   2. 走真实 HTTP：登录拿 cookie → 访问三个布局的页面
 *      （admin 布局 /admin/、app 布局 /albums、site 布局 /albums/{id}）
 *   3. 解析 .user-dropdown-head 的两行文字，断言第二行是邮箱而不是「角色 · 邮箱」
 *   4. 断言用邮箱登录、用用户名登录都能进
 *
 * 本脚本只读不写，不改任何数据。
 *
 * 用法：php tools/verify_account_label.php [baseUrl]
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/helpers.php';

$base = $argv[1] ?? 'http://127.0.0.1:8102';
$dbFile = __DIR__ . '/../storage/database.sqlite';

$cookies = [];

function http(string $url, array $opts = []): array
{
    global $cookies;

    $ch = curl_init($url);
    $baseOpts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROXY        => '',      // 本机设了 http_proxy，必须强制直连
        CURLOPT_HEADER        => true,
        CURLOPT_TIMEOUT       => 20,
    ];

    // 手动带 cookie：本机 curl 的 COOKIEJAR 不落盘（samesite=lax 的 Set-Cookie 被丢弃），
    // 所以自己从响应头里收集并存进 $cookies。
    if ($cookies !== []) {
        $pairs = [];
        foreach ($cookies as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }
        $baseOpts[CURLOPT_COOKIE] = implode('; ', $pairs);
    }

    curl_setopt_array($ch, $baseOpts + $opts);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $raw = (string) $raw;
    $split = strpos($raw, "\r\n\r\n");
    $headers = $split === false ? $raw : substr($raw, 0, $split);
    $body = $split === false ? '' : substr($raw, $split + 4);

    if (preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]*)/mi', $headers, $m, PREG_SET_ORDER)) {
        foreach ($m as $c) {
            $cookies[trim($c[1])] = trim($c[2]);
        }
    }

    return ['code' => $code, 'body' => $body];
}

$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $fail;
    echo '  [' . ($ok ? 'PASS' : 'FAIL') . '] ' . $label . ($detail !== '' ? '  → ' . $detail : '') . "\n";
    if (!$ok) {
        $fail++;
    }
}

/**
 * 解析页面里 .user-dropdown-head 的两行文字。
 *
 * @return array{name: string, sub: string}
 */
function dropdownHead(string $html): array
{
    $name = '';
    $sub = '';
    if (preg_match('/<div class="user-dropdown-head">(.*?)<\/div>/s', $html, $m)) {
        $block = $m[1];
        if (preg_match('/<strong>(.*?)<\/strong>/s', $block, $sm)) {
            $name = trim(html_entity_decode($sm[1], ENT_QUOTES, 'UTF-8'));
        }
        if (preg_match('/<span[^>]*>(.*?)<\/span>/s', $block, $sm)) {
            $sub = trim(html_entity_decode($sm[1], ENT_QUOTES, 'UTF-8'));
        }
    }
    return ['name' => $name, 'sub' => $sub];
}

echo "== 1. 数据层：账号邮箱 ==\n";
$db = new PDO('sqlite:' . $dbFile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$users = $db->query('SELECT id, name, email, is_admin FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

check('users 表非空', $users !== [], count($users) . ' 个账号');
foreach ($users as $u) {
    $mail = trim((string) $u['email']);
    $ok = $mail !== '' && filter_var($mail, FILTER_VALIDATE_EMAIL) !== false;
    check(
        "账号 #{$u['id']} ({$u['name']}) 的 email 是合法邮箱",
        $ok,
        'email=' . var_export($mail, true)
    );
}

// 拿到 admin 账号的邮箱，后面的页面校验要跟它逐字比对
$adminEmail = '';
$adminName = '';
foreach ($users as $u) {
    if ((int) $u['is_admin'] === 1) {
        $adminEmail = trim((string) $u['email']);
        $adminName = trim((string) $u['name']);
        break;
    }
}
check('取到管理员账号的绑定邮箱', $adminEmail !== '', $adminEmail);

echo "\n== 2. 登录（用用户名） ==\n";
$r = http($base . '/login');
$csrf = '';
if (preg_match('/name="_token"\s+value="([^"]+)"/', $r['body'], $m)) {
    $csrf = $m[1];
}
check('登录页取到 CSRF token', $csrf !== '', $csrf !== '' ? substr($csrf, 0, 12) . '…' : '未找到');

$r = http($base . '/login', [
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_token'   => $csrf,
        'account'  => $adminName,
        'password' => 'admin',
    ]),
]);
check('用用户名登录提交成功', in_array($r['code'], [302, 200], true), 'HTTP ' . $r['code']);

echo "\n== 3. 三种布局的头部副标题 ==\n";

// 找一个相册 id 用来验证 site 布局（相册详情页用 layouts/site）
$albumId = 0;
$albumRow = $db->query('SELECT id FROM albums ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if ($albumRow !== false) {
    $albumId = (int) $albumRow['id'];
}

$pages = [
    'admin 布局 (/admin/)'   => $base . '/admin/',
    'app 布局 (/albums)'     => $base . '/albums',
];
if ($albumId > 0) {
    $pages['site 布局 (/albums/' . $albumId . ')'] = $base . '/albums/' . $albumId;
}

foreach ($pages as $label => $url) {
    $r = http($url);
    check("{$label} 可访问", $r['code'] === 200, 'HTTP ' . $r['code']);

    $head = dropdownHead($r['body']);
    check("{$label} 头部第一行是昵称", $head['name'] === $adminName, '实际="' . $head['name'] . '"');
    check(
        "{$label} 头部第二行是账号绑定的邮箱",
        $head['sub'] === $adminEmail,
        '实际="' . $head['sub'] . '" 期望="' . $adminEmail . '"'
    );
    check(
        "{$label} 副标题不再是「角色 · xxx」",
        !str_contains($head['sub'], '管理员') && !str_contains($head['sub'], '普通用户'),
        '实际="' . $head['sub'] . '"'
    );
    check("{$label} 副标题含 @ （邮箱形态）", str_contains($head['sub'], '@'), '实际="' . $head['sub'] . '"');
    check(
        "{$label} 头部带角色悬停提示",
        (bool) preg_match('/<span title="(管理员|普通用户)">/', $r['body']),
        ''
    );
}

echo "\n== 4. 用邮箱登录 ==\n";
// 换一个新会话，避免沿用上一个登录态
$cookies = [];
$r = http($base . '/login');
$csrf2 = '';
if (preg_match('/name="_token"\s+value="([^"]+)"/', $r['body'], $m)) {
    $csrf2 = $m[1];
}
$r = http($base . '/login', [
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_token'   => $csrf2,
        'account'  => $adminEmail,
        'password' => 'admin',
    ]),
]);
check('用邮箱登录提交成功', in_array($r['code'], [302, 200], true), 'HTTP ' . $r['code']);

$r = http($base . '/albums');
$head = dropdownHead($r['body']);
check('邮箱登录后头部副标题仍是该邮箱', $head['sub'] === $adminEmail, '实际="' . $head['sub'] . '"');

echo "\n" . ($fail === 0 ? '全部通过' : "失败 {$fail} 项") . "\n";
exit($fail === 0 ? 0 : 1);
