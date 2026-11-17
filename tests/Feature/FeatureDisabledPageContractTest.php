<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 功能关闭时的美化提示页契约。
 * 背景：站主关掉某功能后，原来 abort(404) 会被宝塔的 nginx 拦成丑陋的
 * 「404 Not Found / nginx」。改成 Response::featureDisabled() 输出 HTTP 200
 * 的友好页面，nginx 不会再接管。
 */
class FeatureDisabledPageContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_response_has_feature_disabled_method(): void
    {
        $code = (string) file_get_contents($this->root() . '/app/Core/Response.php');
        $this->assertStringContainsString('function featureDisabled(', $code);
        // 必须返回 200，否则 nginx 的 fastcgi_intercept_errors 会换成默认错误页
        $this->assertStringContainsString('new SymfonyResponse($html, 200,', $code);
        $this->assertStringContainsString('暂未开放', $code);
        $this->assertStringContainsString('返回首页', $code);
    }

    public function test_feature_off_switches_use_friendly_page(): void
    {
        $expect = [
            'app/Http/Controllers/ExploreController.php' => ['图片广场', '用户主页'],
            'app/Http/Controllers/ShopController.php'    => ['商城'],
            'app/Http/Controllers/SupportController.php'  => ['工单', '意见反馈'],
            'app/Http/Controllers/OauthController.php'    => ['第三方登录'],
        ];
        foreach ($expect as $file => $features) {
            $code = (string) file_get_contents($this->root() . '/' . $file);
            $this->assertStringContainsString(
                'Response::featureDisabled(',
                $code,
                "{$file} 没用美化提示页"
            );
            foreach ($features as $feature) {
                $this->assertStringContainsString(
                    "featureDisabled('{$feature}'",
                    $code,
                    "{$file} 缺少「{$feature}」的友好提示"
                );
            }
            // 功能开关原来的 "xxx未开放" 不该再走 abort(404)
            $this->assertStringNotContainsString('abort(404, \'' . $features[0] . '未开放', $code);
        }
    }

    public function test_real_resource_missing_still_uses_404(): void
    {
        // 真正的「资源不存在」仍走 404，不能被上面的改动波及
        $album = (string) file_get_contents($this->root() . '/app/Http/Controllers/AlbumController.php');
        $this->assertStringContainsString('abort(404', $album);
        $this->assertStringContainsString('分享链接不存在或已关闭', $album);
    }

    public function test_feature_disabled_renders_friendly_html(): void
    {
        // 直接调方法抛出的 HttpResponseException，取响应验证
        try {
            \App\Core\Response::featureDisabled('图片广场', '测试提示语');
            $this->fail('featureDisabled 应该中断并抛异常');
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            $res = $e->getResponse();
            $this->assertSame(200, $res->getStatusCode(), '功能关闭页必须是 200');
            $this->assertStringContainsString('text/html', $res->headers->get('Content-Type'));
            $html = $res->getContent();
            $this->assertStringContainsString('图片广场', $html);
            $this->assertStringContainsString('该功能暂未开放', $html);
            $this->assertStringContainsString('测试提示语', $html);
            $this->assertStringContainsString('返回首页', $html);
        }
    }
}
