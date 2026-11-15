<?php
/**
 * 探测：普通用户（非 admin）登录后访问 / 渲染什么。
 *
 * 用法：php tools/probe_home_normal.php [baseUrl]
 * 会临时建一个普通账号，跑完自动删除。
 */

$base = $argv[1] ?? 'http://127.0.0.1:8102';
$account = 'probeuser' . substr((string) time(), -6);
$password = 'Probe@123456';

$cookies = [];

function http(string $url, array $opts = []): array
{
    global $cookies;
    $ch = curl_init($url);
    $o = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROXY        => '',
        CURLOPT_HEADER        => true,
        CURLOPT_TIMEOUT       => 20,
    ];
    if ($cookies !== []) {
        $pairs = [];
        foreach ($cookies as $n => $v) {
            $pairs[] = $n . '=' . $v;
        }
        $o[CURLOPT_COOKIE] = implode('; ', $pairs);
    }
    curl_setopt_array($ch, $o + $opts);
    $raw = (string) curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $loc  = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    $split = strpos($raw, "\r\n\r\n");
    $headers = $split === false ? $raw : substr($raw, 0, $split);
    $body    = $split === false ? '' : substr($raw, $split + 4);
    if (preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]*)/mi', $headers, $m, PREG_SET_ORDER)) {
        foreach ($m as $c) {
            $cookies[trim($c[1])] = trim($c[2]);
        }
    }
    return ['code' => $code, 'body' => $body, 'headers' => $headers, 'loc' => $loc];
}

function csrf(string $body): string
{
    return preg_match('/name="_token"\s+value="([^"]+)"/i', $body, $m) ? $m[1] : '';
}

// 直接建账号（绕开注册验证码）
$db = new PDO('sqlite:' . __DIR__ . '/../storage/database.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$cols = [];
foreach ($db->query('PRAGMA table_info(users)') as $r) {
    $cols[] = $r['name'];
}
echo 'users 表列: ' . implode(',', $cols) . "\n";

$st = $db->prepare('INSERT INTO users (name, email, password, is_admin, status, group_id, created_at) VALUES (?,?,?,0,1,1,?)');
$st->execute([
    $account,
    $account . '@probe.local',
    password_hash($password, PASSWORD_DEFAULT),
    date('Y-m-d H:i:s'),
]);
$uid = (int) $db->lastInsertId();
echo "已建普通账号 id={$uid} name={$account}\n";

try {
    $r = http($base . '/login');
    $token = csrf($r['body']);
    $r = http($base . '/login', [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'account'  => $account,
            'password' => $password,
            '_token'   => $token,
        ]),
    ]);
    echo "普通用户登录 POST -> HTTP {$r['code']} Location=" . ($r['loc'] ?: '(无)') . "\n";

    $r = http($base . '/');
    echo "普通用户 GET / -> HTTP {$r['code']}\n";
    preg_match('/<title>(.*?)<\/title>/s', $r['body'], $t);
    echo '   title : ' . trim($t[1] ?? '(无)') . "\n";
    foreach (['landing.css' => '落地页样式', 'land-hero' => '落地页Hero', '控制台' => '控制台'] as $k => $d) {
        echo '   ' . ($str = (str_contains($r['body'], $k) ? '[有]' : '[无]')) . " {$d}\n";
    }
} finally {
    $db->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
    echo "已清理探测账号 id={$uid}\n";
}
