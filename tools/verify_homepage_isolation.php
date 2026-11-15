<?php
/**
 * 主页 / 控制台隔离端到端验证。
 *
 * 需求：登录账号以后不该还能打开主页（/）。
 * 以前主页和控制台共用 / 一个 URL，靠登录态在同一个地址里切换视图 ——
 * 结果「登录后依然能打开主页」在 URL 层面成立。现在拆成两个独立地址：
 *
 *   /          → 营销落地页，游客专属；登录用户访问一律 302 弹到 /dashboard
 *   /dashboard → 用户控制台，登录专属；未登录跳登录页并带 redirect 回跳
 *
 * 本脚本走真实 HTTP 链路验证，只读不写，不改任何数据。
 *
 * 用法：php tools/verify_homepage_isolation.php [baseUrl]
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/helpers.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8102', '/');
$account = getenv('LB_ACCOUNT') ?: 'admin';
$password = getenv('LB_PASSWORD') ?: 'admin';

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

    // 手动带 cookie：本机 curl 的 COOKIEJAR 不落盘，自己从响应头收集
    if ($cookies !== []) {
        $pairs = [];
        foreach ($cookies as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }
        $baseOpts[CURLOPT_COOKIE] = implode('; ', $pairs);
    }

    curl_setopt_array($ch, $baseOpts + $opts);
    $raw = (string) curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $loc  = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);

    $split = strpos($raw, "\r\n\r\n");
    $headers = $split === false ? $raw : substr($raw, 0, $split);
    $body = $split === false ? '' : substr($raw, $split + 4);

    if (preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]*)/mi', $headers, $m, PREG_SET_ORDER)) {
        foreach ($m as $c) {
            $cookies[trim($c[1])] = trim($c[2]);
        }
    }

    return ['code' => $code, 'body' => $body, 'headers' => $headers, 'loc' => $loc];
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

function csrfToken(string $html): string
{
    if (preg_match('/name="_token"\s+value="([^"]+)"/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $html, $m)) {
        return $m[1];
    }
    return '';
}

/**
 * 页面是不是落地页。
 *
 * 落地页特征：lx- 开头的营销类 class、Hero 文案。
 * 控制台特征：侧边栏 nav-item、控制台统计文案。
 */
function looksLanding(string $html): bool
{
    return str_contains($html, 'lx-hero') || str_contains($html, 'lx-btn-primary');
}

function looksConsole(string $html): bool
{
    return str_contains($html, 'nav-item') || str_contains($html, '今日上传');
}

echo "== 1. 游客访问 /（落地页专属）==\n";

$r = http($base . '/');
check('游客访问 / 返回 200', $r['code'] === 200, 'HTTP ' . $r['code']);
check('渲染的是落地页', looksLanding($r['body']), '');
check('不是控制台', !looksConsole($r['body']), '');

preg_match('/<title>(.*?)<\/title>/s', $r['body'], $t);
check('title 是首页', str_contains($t[1] ?? '', '首页'), trim($t[1] ?? '(无)'));

echo "\n== 2. 游客访问 /dashboard（应跳登录页）==\n";

$r = http($base . '/dashboard');
check('未登录访问控制台返回 302', $r['code'] === 302, 'HTTP ' . $r['code']);
check('跳转到登录页', str_contains($r['loc'], '/login'), $r['loc']);
check('带 redirect 回跳参数', str_contains($r['loc'], 'redirect='), $r['loc']);

echo "\n== 3. 落地页的入口文案（游客视角）==\n";

$r = http($base . '/');
// 落地页是纯营销页：登录用户访问 / 会被 302 弹走，所以游客视图里不该出现控制台入口
check('游客落地页主按钮是「免费开始使用」',
    str_contains($r['body'], '免费开始使用'), '');
check('游客落地页没有指向根路径 / 的控制台链接',
    !preg_match('/<a[^>]*href="' . preg_quote($base, '/') . '\/"[^>]*>(?:<[^>]+>\s*)*[^<]*控制台/', $r['body']), '');
check('游客落地页不含控制台入口 /dashboard（登录用户根本到不了这一页）',
    !str_contains($r['body'], '/dashboard'), '');

echo "\n== 4. 登录 ==\n";

$r = http($base . '/login');
$token = csrfToken($r['body']);
check('登录页拿到 CSRF token', $token !== '', $token !== '' ? 'YES' : 'NO');

$r = http($base . '/login', [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'account'  => $account,
        'password' => $password,
        '_token'   => $token,
    ]),
]);
check('登录 POST 返回 302', $r['code'] === 302, 'HTTP ' . $r['code']);
check('登录后跳到 /dashboard', str_contains($r['loc'], '/dashboard'), $r['loc']);

echo "\n== 5. 登录后访问 /（必须弹走）==\n";

$r = http($base . '/');
check('登录后访问 / 返回 302', $r['code'] === 302, 'HTTP ' . $r['code']);
check('Location 指向 /dashboard', str_contains($r['loc'], '/dashboard'), $r['loc']);
check('响应体没有落地页内容（真的挡住了）', !looksLanding($r['body']), '');

echo "\n== 6. 登录后访问 /dashboard（控制台）==\n";

$r = http($base . '/dashboard');
check('控制台返回 200', $r['code'] === 200, 'HTTP ' . $r['code']);
check('渲染的是控制台', looksConsole($r['body']), '');
check('不是落地页', !looksLanding($r['body']), '');

preg_match('/<title>(.*?)<\/title>/s', $r['body'], $t);
check('title 是控制台', str_contains($t[1] ?? '', '控制台'), trim($t[1] ?? '(无)'));

echo "\n== 7. 控制台页面里的入口链接==\n";

$r = http($base . '/dashboard');
check('侧边栏「上传图片」指向 /upload',
    str_contains($r['body'], 'href="' . $base . '/upload"'), '');
check('侧边栏没有指回根路径的链接',
    !preg_match('/<a[^>]*href="' . preg_quote($base, '/') . '\/"[^>]*>\s*(?:<[^>]+>\s*)*[^<]*(?:控制台|上传图片)/', $r['body']), '');

$r = http($base . '/images');
check('图片管理页「上传」按钮指向 /upload', str_contains($r['body'], 'href="' . $base . '/upload"'), '');

echo "\n== 8. 后台「回到前台」==\n";

$r = http($base . '/admin');
check('后台可访问', $r['code'] === 200, 'HTTP ' . $r['code']);
check('后台「回到前台」指向 /dashboard', str_contains($r['body'], 'href="' . $base . '/dashboard"'), '');

echo "\n== 9. 退出登录后 / 恢复落地页 ==\n";

// 登录成功后 session 会重新生成，CSRF token 也跟着换 —— 退出要用新 token
$probe = http($base . '/dashboard');
$logoutToken = csrfToken($probe['body']);
$r = http($base . '/logout', [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['_token' => $logoutToken]),
]);
check('退出登录返回 302', $r['code'] === 302, 'HTTP ' . $r['code']);

$cookies = [];
$r = http($base . '/');
check('游客访问 / 仍是 200', $r['code'] === 200, 'HTTP ' . $r['code']);
check('又变回落地页', looksLanding($r['body']), '');

echo "\n" . ($fail === 0
    ? "全部通过：主页与控制台已彻底隔离\n"
    : "有 {$fail} 项未通过\n");

exit($fail === 0 ? 0 : 1);
