<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Core;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * 响应输出层。
 *
 *原先是 header() + echo + exit；2.0 改成抛出 HttpResponseException，
 * 由 Laravel 的异常处理器接管。效果一致（都是立刻中断本次请求并输出内容），
 * 但不再有裸 exit，请求生命周期交给框架管。
 */
class Response
{
    public static function statusText(int $status): string
    {
        $map = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => '请求参数错误',
            401 => '未认证',
            403 => '无权访问',
            404 => '页面不存在',
            405 => '请求方法不允许',
            413 => '文件过大',
            419 => '页面已过期',
            422 => '数据校验失败',
            429 => '请求过于频繁',
            500 => '服务器内部错误',
            503 => '服务不可用',
        ];
        return $map[$status] ?? 'Error';
    }

    /**
     * JSON 编码选项与既有行为保持一致（不转义中文与斜杠），
     * 这样接口返回的字节完全一样，客户端不用改。
     */
    private static function encode($data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? '' : $json;
    }

    public static function json($data, int $status = 200): never
    {
        throw new HttpResponseException(new SymfonyResponse(
            self::encode($data),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        ));
    }

    public static function api(bool $status, string $message = '', $data = null, int $httpStatus = 200): never
    {
        $payload = ['status' => $status, 'message' => $message, 'data' => $data];
        if ($httpStatus === 200 && !$status) {
            $httpStatus = 422;
        }
        self::json($payload, $httpStatus);
    }

    /**
     * 跳转。
     *
     *原先是 header('Location: ...') + exit，正文为空。
     * 2.0 改成抛 HttpResponseException，效果一样（立刻中断本次请求），
     * 响应体同样保持为空，客户端看到的字节与既有行为一致。
     *
     * 这是跳转语义的唯一来源：helpers.php 的 redirect() 与
     * App\Core\Auth 里的登录校验都走这里，不依赖全局函数谁先被定义。
     */
    public static function redirect(string $path, int $status = 302): never
    {
        if (!preg_match('#^https?://#i', $path)) {
            $path = url($path);
        }
        $response = new RedirectResponse($path, $status);
        //既有的响应体是空的（只发 Location 头），这里也保持一致
        $response->setContent('');
        throw new HttpResponseException($response);
    }

    public static function html(string $content, int $status = 200): never
    {
        throw new HttpResponseException(new SymfonyResponse(
            $content,
            $status,
            ['Content-Type' => 'text/html; charset=utf-8']
        ));
    }

    public static function text(string $content, int $status = 200): never
    {
        throw new HttpResponseException(new SymfonyResponse(
            $content,
            $status,
            ['Content-Type' => 'text/plain; charset=utf-8']
        ));
    }

    /**
     * 错误页。全站使用统一样式，不同入口看到的错误页保持一致。
     *
     * errorPageResponse() 返回响应对象（给 Laravel 的异常渲染回调用），
     * errorPage() 直接抛出去（给业务代码用）。
     */
    public static function errorPageResponse(int $status, string $message = ''): SymfonyResponse
    {
        if ($status === 404 && $message === '') {
            $message = '页面不存在';
        }
        $title = $status . ' · ' . self::statusText($status);
        $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $home = htmlspecialchars(base_url() . '/', ENT_QUOTES, 'UTF-8');
        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<style>
html,body{height:100%%;margin:0}
body{display:flex;align-items:center;justify-content:center;background:#f6f7fb;color:#1f2430;
font:14px/1.7 -apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif}
.box{max-width:680px;padding:40px;text-align:center}
.code{font-size:88px;font-weight:800;letter-spacing:-4px;color:#4c6ef5;line-height:1}
.msg{white-space:pre-wrap;word-break:break-word;margin:18px 0 26px;color:#4b5563;font-size:13px;text-align:left;
background:#fff;border:1px solid #e6e8ef;border-radius:12px;padding:16px}
a.btn{display:inline-block;padding:10px 22px;border-radius:10px;background:#4c6ef5;color:#fff;text-decoration:none}
</style>
</head>
<body><div class="box"><div class="code">{$status}</div><div class="msg">{$safeMessage}</div>
<a class="btn" href="{$home}">返回首页</a></div></body></html>
HTML;
        return new SymfonyResponse($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function errorPage(int $status, string $message = ''): never
    {
        throw new HttpResponseException(self::errorPageResponse($status, $message));
    }

    /**
     * 功能被站主关闭时显示的友好提示页。
     *
     * 刻意返回 HTTP 200 而不是 404：功能关闭是站点正常的配置状态，不是错误；
     * 而且不少宝塔环境开了 fastcgi_intercept_errors，程序返回的 4xx 会被 nginx
     * 换成它自己的默认错误页（就是那个丑陋的「404 Not Found / nginx」）。
     * 用 200 就能稳定显示下面这张美化页，不会被中间件接管。
     */
    public static function featureDisabled(string $feature, string $tip = ''): never
    {
        $title = htmlspecialchars($feature, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $tipText = $tip !== ''
            ? '<p class="tip">' . htmlspecialchars($tip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
            : '<p class="tip">该功能暂时没有开放，请稍后再来看看。</p>';
        $home = htmlspecialchars(base_url() . '/', ENT_QUOTES, 'UTF-8');
        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title} · 暂未开放</title>
<style>
*{box-sizing:border-box}
html,body{min-height:100%;margin:0}
body{display:flex;align-items:center;justify-content:center;padding:24px;background:linear-gradient(160deg,#f6f7fb,#eef1f8);
color:#1f2430;font:15px/1.7 -apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif}
.card{width:100%;max-width:420px;background:#fff;border:1px solid #e6e8ef;border-radius:18px;
box-shadow:0 18px 50px -28px rgba(76,110,245,.45);padding:40px 32px;text-align:center}
.icon{width:66px;height:66px;margin:0 auto 20px;border-radius:50%;display:flex;align-items:center;justify-content:center;
background:#eef2ff;color:#4c6ef5}
.icon svg{width:32px;height:32px}
h1{font-size:20px;margin:0 0 8px;font-weight:700}
.sub{font-size:13px;color:#8b93a7;margin:0 0 18px}
.tip{font-size:14px;color:#4b5563;margin:0 0 26px;background:#f8f9fc;border:1px solid #eef0f5;
border-radius:12px;padding:14px 16px}
.btn{display:inline-block;padding:11px 26px;border-radius:10px;background:#4c6ef5;color:#fff;text-decoration:none;font-weight:600}
.btn:hover{background:#3b5bdb}
@media(max-width:480px){.card{padding:32px 22px}h1{font-size:18px}}
</style>
</head>
<body>
<div class="card">
<div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 15v2"/></svg></div>
<h1>{$title}</h1>
<p class="sub">该功能暂未开放</p>
{$tipText}
<a class="btn" href="{$home}">返回首页</a>
</div>
</body></html>
HTML;
        throw new HttpResponseException(new SymfonyResponse($html, 200, ['Content-Type' => 'text/html; charset=utf-8']));
    }

    /**
     * 文件输出。走 Symfony 的 BinaryFileResponse，大文件仍然是流式发送，
     * 不会整个读进内存。
     */
    public static function file(string $path, ?string $downloadName = null, int $maxAge = 86400, bool $private = false): never
    {
        if (!is_file($path)) {
            abort(404, '文件不存在');
        }
        $size = filesize($path);
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'css'   => 'text/css; charset=utf-8',
            'js'    => 'application/javascript; charset=utf-8',
            'json'  => 'application/json; charset=utf-8',
            'svg'   => 'image/svg+xml',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            'bmp'   => 'image/bmp',
            'ico'   => 'image/x-icon',
            'avif'  => 'image/avif',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
        ];

        $mime = $map[$extension]
            ?? (function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream');

        $response = new BinaryFileResponse($path);
        $response->setStatusCode(200);
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Content-Length', (string) $size);

        // 缩略图这类内容会随审核状态变化，缓存时间交给调用方控制，0 表示完全不缓存
        if ($maxAge > 0) {
            // 同一地址对不同身份可能是不同内容（加密图：本人清晰、访客马赛克），禁止共享缓存串号
            $response->headers->set('Cache-Control', ($private ? 'private' : 'public') . ', max-age=' . $maxAge);
            if ($private) {
                $response->headers->set('Vary', 'Cookie');
            }
        } else {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
        }
        $response->headers->set('ETag', '"' . md5($path . $size . filemtime($path)) . '"');
        if ($downloadName !== null) {
            $response->headers->set('Content-Disposition', 'attachment; filename="' . rawurlencode($downloadName) . '"');
        }
        //原先没有这两个头，去掉以保证响应头完全一致
        $response->headers->remove('Accept-Ranges');
        $response->headers->remove('Last-Modified');

        throw new HttpResponseException($response);
    }
}
