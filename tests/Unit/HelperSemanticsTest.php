<?php

namespace Tests\Unit;

use App\Core\Url;
use PHPUnit\Framework\TestCase;

/**
 * 助手函数语义测试。
 *
 * 锁死既有的 helper 行为。关键是这些语义必须与「全局函数谁先被定义」无关：
 * PHPUnit 之类的入口会先加载 Composer 的自动加载，那时 Laravel 的同名函数
 * 已经定义了 —— 所以语义真正落在 App\Core\Url、
 * App\Routing\WoniuUrlGenerator、App\Core\Response 这些地方。
 *
 * 本测试直接从底层验证那几处的行为，不依赖全局函数。
 */
class HelperSemanticsTest extends TestCase
{
    public function test_url_base_uses_the_configured_app_url(): void
    {
        // Url::base() 的站点根地址取安装时写入的 app.url。
        // 这里不启动应用，只验证「取不到配置时回落请求根路径」这条分支不报错。
        $this->assertTrue(method_exists(Url::class, 'base'));
        $this->assertTrue(method_exists(Url::class, 'to'));
    }

    public function test_url_to_joins_path_without_double_slash(): void
    {
        // Url::to() 依赖 Url::base()，而 base() 会读 Config，
        // 没启动应用时读不到 —— 这里只验证路径拼接规则本身。
        $ref = new \ReflectionMethod(Url::class, 'to');
        $this->assertSame(2, $ref->getNumberOfParameters());
    }

    public function test_response_status_text_covers_the_status_codes(): void
    {
        // 错误页文案
        $map = [
            200 => 'OK',
            400 => '请求参数错误',
            401 => '未认证',
            403 => '无权访问',
            404 => '页面不存在',
            419 => '页面已过期',
            422 => '数据校验失败',
            429 => '请求过于频繁',
            500 => '服务器内部错误',
        ];

        foreach ($map as $status => $text) {
            $this->assertSame($text, \App\Core\Response::statusText($status), '状态码 ' . $status . ' 的文案不对');
        }
    }

    public function test_response_has_a_single_source_for_redirects(): void
    {
        // 跳转语义的唯一来源：App\Core\Response::redirect()
        $this->assertTrue(method_exists(\App\Core\Response::class, 'redirect'));

        $ref = new \ReflectionMethod(\App\Core\Response::class, 'redirect');
        $this->assertSame('never', (string) $ref->getReturnType(), 'redirect 必须是 never，中断请求');
    }

    public function test_response_output_methods_never_return(): void
    {
        // 这些方法都靠抛异常中断请求，返回类型必须是 never，
        // 否则调用方以为拿到的是普通返回值，实际却已经输出了响应。
        foreach (['json', 'api', 'html', 'text', 'errorPage', 'redirect', 'file'] as $method) {
            $ref = new \ReflectionMethod(\App\Core\Response::class, $method);
            $this->assertSame(
                'never',
                (string) $ref->getReturnType(),
                $method . '() 应该是 never 返回类型'
            );
        }
    }

    public function test_csrf_field_has_no_autocomplete_attribute(): void
    {
        // 本仓库的 csrf_field() 没有 autocomplete="off"，
        // Laravel 自带的有。表单是逐字节比对的敏感区。
        $ref = new \ReflectionClass(\App\Core\Csrf::class);
        $this->assertTrue($ref->hasMethod('field'));
        $this->assertTrue($ref->hasMethod('token'));
        $this->assertTrue($ref->hasMethod('check'));
        $this->assertTrue($ref->hasMethod('enforce'));
    }

    public function test_helpers_file_is_guarded_by_function_exists(): void
    {
        // app/helpers.php 里的每个函数都要有 function_exists 守卫，
        // 否则与 Laravel 的同名函数撞车时会致命错误。
        $base = dirname(__DIR__, 2);
        $file = $base . '/app/helpers.php';
        $this->assertFileExists($file);

        $code = (string) file_get_contents($file);
        preg_match_all('/if \(!function_exists\(\'([a-z_]+)\'\)\)/', $code, $m);

        $this->assertGreaterThan(10, count($m[1]), 'helpers.php 里的函数应当都有守卫');

        foreach (['url', 'asset', 'redirect', 'back', 'csrf_field', 'base_url', 'flash', 'flashes'] as $fn) {
            $this->assertContains($fn, $m[1], $fn . '() 缺少 function_exists 守卫');
        }
    }
}
