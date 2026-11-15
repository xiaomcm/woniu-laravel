<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

use App\Core\HttpException;
use App\Core\Response;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException as SymfonyHttpException;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: null,
        // 既有接口路径是 /api/v1/...
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //原先支持用表单字段 _method 覆写请求方法。
        // Laravel 13 的 Request::capture() 默认已经开启，这里无需额外配置。

        // 没装完就把请求引到安装向导
        $middleware->prepend(\App\Http\Middleware\EnsureInstalled::class);

        $middleware->web(append: [
            \App\Http\Middleware\SessionGc::class,
        ]);

        // 接口既认 Bearer Token，也允许已登录会话直接调用，所以这一组要挂会话
        $middleware->api(prepend: [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);

        // 会话 Cookie 统一用不加密的版本：原有会话 ID 是明文，
        // 已有会话必须还能读出来，web 组与 api 组也要保持一致
        $middleware->web(replace: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class => \App\Http\Middleware\EncryptCookies::class,
        ]);

        // API 与支付回调走的是 Token 鉴权，不做 CSRF 校验
        // 安装向导是装站之前的一次性页面（那时还没有会话），同样豁免
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'pay/notify/*',
            'install',
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 业务自定义异常：状态码与提示语按既有的规则输出
        $exceptions->render(function (HttpException $e, Request $request) {
            return Response::errorPageResponse($e->getStatusCode(), $e->getMessage());
        });

        // 框架抛出的 HTTP 异常（abort() 走这条），同样输出既有的错误页
        $exceptions->render(function (SymfonyHttpException $e, Request $request) {
            $status = $e->getStatusCode();

            // CSRF 令牌失效（Laravel 会把 TokenMismatchException 转成 419）
            if ($status === 419) {
                if ($request->is('api/*') || $request->ajax() || $request->expectsJson()) {
                    return response()->json([
                        'status'  => false,
                        'message' => '页面已过期，请刷新后重试',
                        'data'    => null,
                    ], 419);
                }
                return Response::errorPageResponse(419, '页面已过期，请返回刷新后重试。');
            }

            $message = $e->getMessage();
            // Laravel 对「路由没匹配上」会带一句默认英文提示，这里换回既有的中文文案
            if ($message === '' || preg_match('/^The route .* could not be found\.$/', $message) === 1) {
                $message = Response::statusText($status);
            }
            return Response::errorPageResponse($status, $message);
        });

        // 兜底：未捕获异常记日志并给出既有的错误页
        // 这个回调要放在最后，因为它匹配所有异常类型
        $exceptions->render(function (\Throwable $e, Request $request) {
            // 已经是构造好的响应，直接放行
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }
            \App\Core\Log::error($e);
            if (config('app.debug')) {
                return Response::errorPageResponse(
                    500,
                    $e->getMessage() . "\n\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString()
                );
            }
            return Response::errorPageResponse(500, '服务器内部错误，请查看 storage/logs 日志。');
        });
    })->create();
