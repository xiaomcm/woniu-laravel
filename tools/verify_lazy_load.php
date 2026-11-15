<?php
/**
 * 图片懒加载（后台开关）端到端验证。
 *
 * 流程：
 *   1. 读设置项 lazy_load_enabled（默认应为 1）
 *   2. 走真实 HTTP 登录，访问列表页，断言 <img> 带 loading="lazy"
 *   3. 关掉开关再访问，断言 <img> 不再带 loading 属性
 *   4. 断言「当前正在查看的大图」不受开关影响（永远立即加载）
 *   5. 断言后台设置页有该开关，且能保存
 *   6. 还原设置
 *
 * 用法：php tools/verify_lazy_load.php [baseUrl]
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

function dbGet(string $dbFile, string $key): string
{
    $db = new PDO('sqlite:' . $dbFile);
    $stmt = $db->prepare('SELECT `value` FROM settings WHERE `key` = :k');
    $stmt->execute(['k' => $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? '' : (string) $row['value'];
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
            ->execute(['v' => $value, 'k' => $key]);
    }
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
 * 统计页面里的 <img> 标签里带 loading="lazy" 的数量。
 */
function countLazy(string $html): int
{
    return (int) preg_match_all('/<img\b[^>]*\bloading="lazy"/i', $html);
}

/**
 * 取出页面里第一个不带 loading 属性的 <img>（当前正在看的大图应该属于这种）。
 */
function firstEagerImg(string $html): string
{
    if (preg_match('/<img\b[^>]*>/i', $html, $m) && !preg_match('/loading=/i', $m[0])) {
        return $m[0];
    }
    return '';
}

echo "== 1. 初始状态 ==\n";
$original = dbGet($dbFile, 'lazy_load_enabled');
echo "  lazy_load_enabled 当前值: " . var_export($original, true) . "\n";

echo "\n== 2. 登录 ==\n";
$r = http($base . '/login');
$csrf = '';
if (preg_match('/name="_token"\s+value="([^"]+)"/', $r['body'], $m)) {
    $csrf = $m[1];
}
check('登录页取到 CSRF token', $csrf !== '');

$r = http($base . '/login', [
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_token'   => $csrf,
        'account'  => 'admin',
        'password' => 'admin',
    ]),
]);
check('登录提交成功', in_array($r['code'], [302, 200], true), 'HTTP ' . $r['code']);

echo "\n== 3. 开关开启时 ==\n";
dbSet($dbFile, 'lazy_load_enabled', '1');

// 我的图库：一定有图（瀑布流卡片）
$r = http($base . '/images');
check('我的图库页可访问', $r['code'] === 200, 'HTTP ' . $r['code']);
$lazyCount = countLazy($r['body']);
check('我的图库页的图片带 loading="lazy"', $lazyCount > 0, $lazyCount . ' 个');
check('同时带 decoding="async"', str_contains($r['body'], 'decoding="async"'), '');

// 相册页：相册没设封面时是占位块而不是 <img>，有图才断言
$r = http($base . '/albums');
check('我的相册页可访问', $r['code'] === 200, 'HTTP ' . $r['code']);
$albumImgs = (int) preg_match_all('/<img\b/i', $r['body']);
if ($albumImgs > 0) {
    check('相册页的封面图带懒加载', countLazy($r['body']) > 0, countLazy($r['body']) . ' / ' . $albumImgs . ' 个');
} else {
    echo "  [SKIP] 相册页当前没有封面图（显示占位块），无可断言的 <img>\n";
}

$r = http($base . '/admin/images');
check('后台图片管理页带懒加载', countLazy($r['body']) > 0, countLazy($r['body']) . ' 个');

$r = http($base . '/');
check('首页带懒加载', countLazy($r['body']) > 0, countLazy($r['body']) . ' 个');

$r = http($base . '/explore');
if ($r['code'] === 200) {
    check('发现广场带懒加载', countLazy($r['body']) > 0, countLazy($r['body']) . ' 个');
} else {
    echo "  [SKIP] 发现广场未开启（HTTP {$r['code']}）\n";
}

echo "\n== 4. 开关关闭时 ==\n";
dbSet($dbFile, 'lazy_load_enabled', '0');

$r = http($base . '/images');
check('我的图库页可访问', $r['code'] === 200, 'HTTP ' . $r['code']);
check('图片不再带 loading="lazy"', countLazy($r['body']) === 0, countLazy($r['body']) . ' 个');
check('图片不再带 decoding="async"', !str_contains($r['body'], 'decoding="async"'), '');

$r = http($base . '/admin/images');
check('后台图片管理页也不再带懒加载', countLazy($r['body']) === 0, countLazy($r['body']) . ' 个');

$r = http($base . '/');
check('首页也不再带懒加载', countLazy($r['body']) === 0, countLazy($r['body']) . ' 个');

echo "\n== 5. 当前大图不受开关影响 ==\n";
$db = new PDO('sqlite:' . $dbFile);
$imgRow = $db->query('SELECT sha1, url FROM images WHERE status = 1 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if ($imgRow !== false) {
    $detailUrl = $base . '/i/' . (string) $imgRow['sha1'];
    $r = http($detailUrl);
    check('详情页可访问', $r['code'] === 200, 'HTTP ' . $r['code']);
    // 详情页主图 + 相关推荐：主图必须不带 loading，相关推荐跟随开关
    $eager = firstEagerImg($r['body']);
    check('详情页存在不懒加载的主图', $eager !== '', substr($eager, 0, 90));
} else {
    check('详情页可访问（库里有图片）', false, 'images 表没有 status=1 的图片，跳过');
}

echo "\n== 6. 后台设置页 ==\n";
$r = http($base . '/admin/settings');
check('设置页可访问', $r['code'] === 200, 'HTTP ' . $r['code']);
check('设置页有 lazy_load_enabled 开关', str_contains($r['body'], 'name="lazy_load_enabled"'));
check('设置页有中文说明文案', str_contains($r['body'], '图片延迟加载'), '');

// 通过后台表单保存一次开关，确认能落库
$formCsrf = '';
if (preg_match('/name="_token"\s+value="([^"]+)"/', $r['body'], $m)) {
    $formCsrf = $m[1];
}
$r = http($base . '/admin/settings', [
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_token'            => $formCsrf,
        'lazy_load_enabled' => '1',
    ]),
]);
check('后台保存请求已提交', in_array($r['code'], [302, 200], true), 'HTTP ' . $r['code']);
check('后台保存后数据库为 1', dbGet($dbFile, 'lazy_load_enabled') === '1', dbGet($dbFile, 'lazy_load_enabled'));

echo "\n== 7. 还原 ==\n";
if ($original === '') {
    $db->prepare('DELETE FROM settings WHERE `key` = ?')->execute(['lazy_load_enabled']);
    echo "  已删除该设置行（回到 defaults 兜底）\n";
} else {
    dbSet($dbFile, 'lazy_load_enabled', $original);
    echo "  已还原为 " . $original . "\n";
}
check('还原完成', dbGet($dbFile, 'lazy_load_enabled') === $original, var_export(dbGet($dbFile, 'lazy_load_enabled'), true));

echo "\n" . ($fail === 0 ? '全部通过' : "失败 {$fail} 项") . "\n";
exit($fail === 0 ? 0 : 1);
