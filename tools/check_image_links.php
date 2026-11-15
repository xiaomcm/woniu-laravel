<?php
/**
 * 抓图库页面里的图片链接，逐个访问，看哪些 404。
 */
declare(strict_types=1);

require __DIR__ . '/lib/HttpClient.php';

use Woniu\Tools\HttpClient;

$base = $argv[1] ?? 'http://127.0.0.1:8102';
$pages = $argv[2] ?? '/admin/images,/images,/explore';

// 详情页也要看：主图是直接取 images.url 的，跟列表页的缩略图是两套地址
$detailPages = [];
$dbFile = dirname(__DIR__) . '/storage/database.sqlite';
if (is_file($dbFile)) {
    $pdo = new PDO('sqlite:' . $dbFile);
    foreach ($pdo->query(
        'SELECT sha1 FROM images WHERE deleted_at IS NULL AND status = 1 ORDER BY id LIMIT 2'
    ) as $row) {
        $detailPages[] = '/i/' . (string) $row['sha1'];
    }
}
$pages .= ',' . implode(',', $detailPages);

$c = new HttpClient($base);
$t = HttpClient::csrfToken($c->request('/login')['body']);
$c->request('/login', [
    '_token'   => $t,
    'account'  => 'admin',
    'password' => 'admin',
    'redirect' => '/',
]);

$links = [];

foreach (explode(',', $pages) as $page) {
    $page = trim($page);
    if ($page === '') {
        continue;
    }
    $r = $c->request($page);
    echo "===== {$page} (HTTP {$r['status']}) =====\n";

    // 抓所有 src / href
    preg_match_all('/(?:src|href)\s*=\s*"([^"]+)"/i', (string) $r['body'], $m);
    foreach ($m[1] as $u) {
        $u = html_entity_decode($u, ENT_QUOTES, 'UTF-8');
        if (!preg_match('#uploads/|thumbs/|covers/|/i/#', $u)) {
            continue;
        }
        $links[$u] = true;
    }

    if ($m[1] === []) {
        echo "  (页面里没有 src/href)\n";
    }
}

echo "\n共 " . count($links) . " 个图片相关链接，逐个访问：\n\n";

$bad = 0;
foreach (array_keys($links) as $u) {
    // 相对地址补全
    $full = preg_match('#^https?://#i', $u) ? $u : rtrim($base, '/') . '/' . ltrim($u, '/');
    $ch = curl_init($full);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROXY          => '',
        CURLOPT_NOBODY         => false,
    ]);
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    $mark = $status >= 400 ? '✗' : '✓';
    if ($status >= 400) {
        $bad++;
    }
    printf("  %s %-4d %-28s %s\n", $mark, $status, $ctype, substr($u, 0, 100));
}

echo "\n异常链接: {$bad} / " . count($links) . "\n";
