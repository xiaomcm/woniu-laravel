<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

namespace App\Providers;

use App\Auth\WoniuGuard;
use App\Core\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 未安装时还没有 app.key（Laravel 的加密器必须有密钥，原先使用原生会话不需要）。
        // 这里补一个临时密钥，安装向导才能跑起来；装完后 config/installed.php 里的正式密钥接管。
        if ((string) config('app.key') === '') {
            $keyFile = storage_path('framework/install.key');
            if (!is_file($keyFile)) {
                @mkdir(dirname($keyFile), 0755, true);
                @file_put_contents($keyFile, 'base64:' . base64_encode(random_bytes(32)));
            }
            config(['app.key' => trim((string) @file_get_contents($keyFile))]);
        }

        // 自定义会话守卫：会话键名沿用既有的 user_id，老会话能直接续用
        Auth::extend('woniu', function ($app, $name, array $config) {
            $provider = Auth::createUserProvider($config['provider'] ?? null);

            $guard = new WoniuGuard(
                $name,
                $provider,
                $app['session.store'],
                $app['request']
            );

            // cookie jar 必须手动注入：框架自带的 session driver 是在
            // AuthManager::createSessionDriver() 里注入的，自定义 driver 走 extend 回调，
            // 绕过了那一步。不注入的话 logout() → clearUserDataFromStorage() →
            // getCookieJar() 会抛 "Cookie jar has not been set."，点退出登录直接 500。
            $guard->setCookieJar($app['cookie']);

            return $guard;
        });

        // URL 生成器换成自己的实现：
        // Laravel 的全局 url() / asset() 最终都落到容器里的 UrlGenerator，
        // 在这里覆写后，全局函数无论由谁定义，产出的地址都与既有行为一致。
        $this->app->extend('url', function ($url, $app) {
            $assetRoot = null;
            // 框架的 UrlGenerator 没有暴露 assetRoot 的读取方法，这里反射取一下
            if (property_exists($url, 'assetRoot')) {
                $ref = new \ReflectionProperty($url, 'assetRoot');
                $ref->setAccessible(true);
                $assetRoot = $ref->getValue($url);
            }

            return new \App\Routing\WoniuUrlGenerator(
                $app['routes'],
                $app['request'],
                $assetRoot
            );
        });

        // 站点设置（后台可改的那些键值对）整体读进内存
        Setting::boot();

        // CSRF 隐藏域：既有的 csrf_field() 输出没有 autocomplete="off"，
        // Laravel 自带的有。表单是逐字节比对的敏感区，这里在编译期替换掉，
        // 保证页面产出的 HTML 与既有产出逐字节一致。
        \Illuminate\Support\Facades\Blade::prepareStringsForCompilationUsing(
            static function (string $template): string {
                return str_replace(
                    'csrf_field()',
                    '\\App\\Core\\Csrf::field()',
                    $template
                );
            }
        );

        // 表结构自检与增量升级：与既有的行为一致（每次请求前确认一次，缺什么补什么）
        if (\App\Http\Middleware\EnsureInstalled::installed()) {
            \App\Core\Migrator::run();
        }
    }
}
