<?php
/**
 * 后台名称自定义（admin_title）端到端验证。
 *
 * 流程：
 *   1. 读当前 admin_title（应为空）
 *   2. 走真实 HTTP：登录拿 cookie → GET 设置页拿 CSRF → POST 保存自定义名
 *   3. 再次 GET 后台首页，检查侧边栏 .brand-name 的文本
 *   4. 还原（admin_title 置回空）
 *
 * 用法：php tools/verify_admin_title.php [baseUrl]
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/helpers.php';

$base = $argv[1] ?? 'http://127.0.0.1:8102';
$dbFile = __DIR__ . '/../storage/database.sqlite';

$cookieJar = tempnam(sys_get_temp_dir(), 'woniu_cookie_');
$cookies = [];

function http(string $url, array $opts = []): array
{
    global $cookies;

    $ch = curl_init($url);
    $base = [
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
        $base[CURLOPT_COOKIE] = implode('; ', $pairs);
    }

    curl_setopt_array($ch, $base + $opts);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $raw = (string) $raw;
    $split = strpos($raw, "\r\n\r\n");
    $headers = $split === false ? $raw : substr($raw, 0, $split);
    $body = $split === false ? '' : substr($raw, $split + 4);

    // 收集 Set-Cookie
    if (preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]*)/mi', $headers, $m, PREG_SET_ORDER)) {
        foreach ($m as $c) {
            $cookies[trim($c[1])] = trim($c[2]);
        }
    }

    return ['code' => $code, 'body' => $body];
}

function dbSet(string $dbFile, string $key, string $value): void
{
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->prepare('SELECT `value` FROM settings WHERE `key` = :k');
    $stmt->execute(['k' => $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        $db->prepare('INSERT INTO settings (`key`, `value`) VALUES (:k, :v)')
            ->execute(['k' => $key, 'v' => $value]);
    } else {
        $db->prepare('UPDATE settings SET `value` = :v WHERE `key` = :k')
            ->execute(['k' => $key, 'v' => $value]);
    }
}

function dbGet(string $dbFile, string $key): string
{
    $db = new PDO('sqlite:' . $dbFile);
    $stmt = $db->prepare('SELECT `value` FROM settings WHERE `key` = :k');
    $stmt->execute(['k' => $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? '' : (string) $row['value'];
}

function dbDel(string $dbFile, string $key): void
{
    $db = new PDO('sqlite:' . $dbFile);
    $db->prepare('DELETE FROM settings WHERE `key` = :k')->execute(['k' => $key]);
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

echo "== 1. 初始状态 ==\n";
$original = dbGet($dbFile, 'admin_title');
echo "  admin_title 当前值: " . var_export($original, true) . "\n";
check('初始为空（走「站点名 后台」回落）', $original === '', var_export($original, true));

echo "\n== 2. 登录 ==\n";
$r = http($base . '/login');
$loginPage = $r['body'];
$csrf = '';
if (preg_match('/name="_token"\s+value="([^"]+)"/', $loginPage, $m)) {
    $csrf = $m[1];
}
check('登录页取到 CSRF token', $csrf !== '', $csrf !== '' ? substr($csrf, 0, 12) . '…' : '未找到');

$r = http($base . '/login', [
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_token'   => $csrf,
        'account'  => 'admin',
        'password' => 'admin',
    ]),
]);
check('登录提交', in_array($r['code'], [302, 200], true), 'HTTP ' . $r['code']);

echo "\n== 3. 设置页读取 ==\n";
$r = http($base . '/admin/settings');
check('设置页可访问', $r['code'] === 200, 'HTTP ' . $r['code']);
$settingsHtml = $r['body'];
check('设置页含「后台名称」字段', strpos($settingsHtml, '后台名称') !== false);
check('设置页有 admin_title 输入框', strpos($settingsHtml, 'name="admin_title"') !== false);

// 「后台名称」必须紧跟在「站点描述」后面
$descPos = strpos($settingsHtml, 'name="site_description"');
$titlePos = strpos($settingsHtml, 'name="admin_title"');
check('「后台名称」排在「站点描述」之后', $descPos !== false && $titlePos !== false && $titlePos > $descPos,
    '描述位置=' . var_export($descPos, true) . ' 后台名位置=' . var_export($titlePos, true));

$formCsrf = '';
if (preg_match('/name="_token"\s+value="([^"]+)"/', $settingsHtml, $m)) {
    $formCsrf = $m[1];
}
check('设置页取到 CSRF token', $formCsrf !== '', $formCsrf !== '' ? substr($formCsrf, 0, 12) . '…' : '未找到');

echo "\n== 4. 保存自定义后台名称 ==\n";
$custom = '蜗牛图床 WONIU 后台中心';
$r = http($base . '/admin/settings', [
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_token'           => $formCsrf,
        'admin_title'      => $custom,
        'site_name'        => dbGet($dbFile, 'site_name') ?: '蜗牛图床',
        'site_description' => dbGet($dbFile, 'site_description'),
        'site_url'         => dbGet($dbFile, 'site_url'),
        'site_icp'         => dbGet($dbFile, 'site_icp'),
        'site_footer'      => dbGet($dbFile, 'site_footer'),
    ]),
]);
check('保存请求已提交', in_array($r['code'], [302, 200], true), 'HTTP ' . $r['code']);
check('数据库已写入 admin_title', dbGet($dbFile, 'admin_title') === $custom, dbGet($dbFile, 'admin_title'));

echo "\n== 5. 后台首页侧边栏验证 ==\n";
$r = http($base . '/admin/');
check('后台首页可访问', $r['code'] === 200, 'HTTP ' . $r['code']);
$adminHtml = $r['body'];

$brand = '';
if (preg_match('/<span class="brand-name">(.*?)<\/span>/s', $adminHtml, $m)) {
    $brand = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
}
check('侧边栏显示自定义后台名', $brand === $custom, '实际="' . $brand . '" 期望="' . $custom . '"');

echo "\n== 6. 还原 ==\n";
// 还原成「没有这一行」：Setting::load() 会用 defaults() 兜底成空串，
// 侧栏自然回落成「{站点名} 后台」
dbDel($dbFile, 'admin_title');
$r = http($base . '/admin/');
$brand2 = '';
if (preg_match('/<span class="brand-name">(.*?)<\/span>/s', $r['body'], $m)) {
    $brand2 = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
}
$siteName = dbGet($dbFile, 'site_name') ?: '蜗牛图床';
check('还原后回落为「站点名 后台」', $brand2 === $siteName . ' 后台', '实际="' . $brand2 . '" 期望="' . $siteName . ' 后台"');
check('数据库已还原（该行已删除）', dbGet($dbFile, 'admin_title') === '', var_export(dbGet($dbFile, 'admin_title'), true));

@unlink($cookieJar);

echo "\n" . ($fail === 0 ? '全部通过' : "失败 {$fail} 项") . "\n";
exit($fail === 0 ? 0 : 1);
