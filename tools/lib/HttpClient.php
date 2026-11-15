<?php
/**
 * 站点自检用的极简 HTTP 客户端。
 *
 * 不用 curl 的 cookie jar 文件：那玩意儿的文件路径一旦含中文（Windows 用户名），
 * curl 会写不进去，会话 cookie 丢失，登录后每个请求都被判成 419。
 * 这里自己维护一份内存里的 cookie 罐，跨请求传递。
 */

declare(strict_types=1);

namespace Woniu\Tools;

/**
 * 带 cookie 罐的 HTTP 客户端。
 */
class HttpClient
{
    /** @var array<string, string> cookie 名 => 值 */
    private array $cookies = [];

    private string $base;

    public function __construct(string $base)
    {
        $this->base = rtrim($base, '/');
    }

    /**
     * 发一个请求。
     *
     * @param  string     $path  相对路径或完整 URL
     * @param  array|null $post  POST 参数，null 表示 GET
     * @return array{status:int, body:string}
     */
    public function request(string $path, ?array $post = null): array
    {
        $url = str_starts_with($path, 'http') ? $path : $this->base . '/' . ltrim($path, '/');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER         => true,
            // 本地测试直连，不走系统代理。
            // 机器上设了 http_proxy 时，curl 会把请求交给代理，
            // 代理连不上本地端口时会返回 502 而不是真正的连接错误，干扰判断。
            CURLOPT_PROXY          => '',
        ]);

        if ($this->cookies !== []) {
            $pairs = [];
            foreach ($this->cookies as $name => $value) {
                $pairs[] = $name . '=' . $value;
            }
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $pairs));
        }

        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }

        $raw = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        // 拆分响应头与正文
        $headers = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);

        // 收集 Set-Cookie
        if (preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]*)/mi', $headers, $m, PREG_SET_ORDER)) {
            foreach ($m as $c) {
                $name = trim($c[1]);
                $value = trim($c[2]);
                if ($value === '' || $value === 'deleted') {
                    unset($this->cookies[$name]);
                    continue;
                }
                $this->cookies[$name] = $value;
            }
        }

        return ['status' => $status, 'body' => $body];
    }

    /**
     * 探活：确认某个地址上的服务在跑。
     *
     * 对比脚本开跑前先探一下，
     * 否则服务没起时会把所有 URL 都报成「差异」（状态码 0），把真问题淹掉。
     *
     * @return array{ok: bool, status: int, error: string}
     */
    public static function ping(string $base): array
    {
        $ch = curl_init(rtrim($base, '/') . '/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROXY          => '',
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = (string) curl_error($ch);
        curl_close($ch);

        return [
            'ok'     => $status > 0,
            'status' => $status,
            'error'  => $error,
        ];
    }

    /**
     * 从页面 HTML 里取 CSRF 令牌。
     */
    public static function csrfToken(string $html): string
    {
        if (preg_match('/name="_token"[^>]*value="([^"]+)"/', $html, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * 归一化：抹掉环境差异导致的内容变化，便于比较页面响应。
     *
     * 抹掉的是这些：
     *   - CSRF 令牌（每次请求都不一样）
     *   - 静态资源版本号（文件 mtime 随部署变化）
     *   - 时间戳（请求时间点不同）
     *   - 随机生成的临时令牌
     *   - 「运行日志」tab 上的文件数（取决于跑了多久留了几个日志文件，
     *     是环境状态不是功能行为，日志文件数量随运行时长变化）
     */
    public static function normalize(string $body): string
    {
        // CSRF 令牌
        $body = preg_replace('/(name="_token"[^>]*value=")[^"]+(")/', '$1TOKEN$2', $body);
        $body = preg_replace('/(name="csrf-token" content=")[^"]+(")/', '$1TOKEN$2', $body);
        // 静态资源版本号
        $body = preg_replace('/(\.css\?v=)[0-9]+/', '$1V', $body);
        $body = preg_replace('/(\.js\?v=)[0-9]+/', '$1V', $body);
        // 随机生成的临时令牌
        $body = preg_replace('/(wn_[A-Za-z0-9]{20,})/', 'WNTOKEN', $body);
        $body = preg_replace('/(data-token=")[^"]+(")/', '$1TOKEN$2', $body);
        // 时间戳
        $body = preg_replace('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', 'TS', $body);
        // 「运行日志」tab 的文件数：只针对 tab=files 那个 tab，操作日志的条数要保留对比
        $body = preg_replace(
            '/(tab=files"[^>]*>\s*运行日志\s*<span class="tab-count">)\d+(<\/span>)/',
            '$1FILES$2',
            (string) $body
        );
        // 站点地址（域名与端口随部署环境变化）属于环境属性，
        // 不属于功能差异，比对时统一抹掉主机与端口。
        $body = preg_replace('#https?://[^/"\'\s]+#', 'HOSTPORT', (string) $body);
        // 空行差异（模板转换残留）
        $body = str_replace("\r\n", "\n", (string) $body);
        return (string) preg_replace("/\n{2,}/", "\n", $body);
    }
}
