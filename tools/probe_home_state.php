<?php
/**
 * 探测：登录前后访问 / 分别渲染什么。
 *
 * 用法：php tools/probe_home_state.php [baseUrl]
 */

$base = $argv[1] ?? 'http://127.0.0.1:8102';
$account = $argv[2] ?? 'admin';
$password = $argv[3] ?? 'admin';

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
    if (preg_match('/name="_token"\s+value="([^"]+)"/i', $body, $m)) {
        return $m[1];
    }
    return '';
}

function describe(string $label, array $r): void
{
    echo "== {$label} => HTTP {$r['code']}";
    if ($r['loc'] !== '') {
        echo "  Location: {$r['loc']}";
    }
    echo "\n";
    preg_match('/<title>(.*?)<\/title>/s', $r['body'], $t);
    echo "   title : " . trim($t[1] ?? '(无)') . "\n";
    $marks = [
        'landing.css'      => '落地页样式表',
        'land-hero'        => '落地页 Hero',
        'lx-nav'           => '落地页导航',
        '控制台'            => '控制台文案',
        '我的图库'          => '我的图库入口',
        '今日上传'          => '控制台统计',
        'data-user-menu'   => '已登录用户菜单',
        'landing'          => 'landing 关键字',
    ];
    foreach ($marks as $k => $desc) {
        if (str_contains($r['body'], $k)) {
            echo "   [有] {$desc} ({$k})\n";
        }
    }
}

$r = http($base . '/');
describe('未登录 GET /', $r);

$r = http($base . '/login');
$token = csrf($r['body']);
echo "csrf token: " . (strlen($token) ? '取得' : '未取得') . "\n";

$r = http($base . '/login', [
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'account'  => $account,
        'password' => $password,
        '_token'   => $token,
    ]),
]);
describe('登录 POST /login', $r);

$r = http($base . '/');
describe('登录后 GET /', $r);

$r = http($base . '/albums');
describe('登录后 GET /albums', $r);
