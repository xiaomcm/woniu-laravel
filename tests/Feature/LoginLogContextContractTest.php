<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 登录页注册入口 + 登录日志的地区/IP/设备 契约。
 */
class LoginLogContextContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_login_page_has_register_link(): void
    {
        $view = (string) file_get_contents($this->root() . '/resources/views/auth/login.blade.php');
        $this->assertStringContainsString("url('forgot')", $view);
        $this->assertStringContainsString('注册账号', $view);
        $this->assertStringContainsString("url('register')", $view);
        // 注册链接始终显示：站点关注册时由注册页自己提示，不在登录页藏链接
        $this->assertStringNotContainsString('register_enabled', $view);
    }

    public function test_log_table_has_location_and_device(): void
    {
        $def = (string) file_get_contents($this->root() . '/app/Core/SchemaDef.php');
        $this->assertStringContainsString("'location' => ['t' => 'string', 'len' => 128", $def);
        $this->assertStringContainsString("'device'   => ['t' => 'string', 'len' => 128", $def);
        $mig = (string) file_get_contents($this->root() . '/app/Core/Migrator.php');
        $this->assertStringContainsString("'logs' => [", $mig);
        $this->assertStringContainsString('private const VERSION = 11', $mig);
    }

    public function test_device_parsing_is_offline_and_readable(): void
    {
        $this->assertSame('Windows · Chrome', \App\Services\LoginContext::device(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0 Safari/537.36'
        ));
        $this->assertSame('iPhone · Safari', \App\Services\LoginContext::device(
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148 Safari/604.1'
        ));
        $this->assertStringContainsString('微信', \App\Services\LoginContext::device(
            'Mozilla/5.0 (Linux; Android 13) Chrome/120 Mobile MicroMessenger/8.0'
        ));
        $this->assertSame('未知设备', \App\Services\LoginContext::device(''));
    }

    public function test_location_handles_local_and_invalid_without_network(): void
    {
        // 内网 IP 纯离线判断，不依赖外部接口
        $this->assertSame('本地网络', \App\Services\LoginContext::location('127.0.0.1'));
        $this->assertSame('本地网络', \App\Services\LoginContext::location('192.168.1.1'));
        $this->assertSame('未知地区', \App\Services\LoginContext::location('not-an-ip'));
    }

    public function test_login_writes_location_and_device(): void
    {
        $code = (string) file_get_contents($this->root() . '/app/Http/Controllers/AuthController.php');
        $this->assertStringContainsString('LoginContext::location(', $code);
        $this->assertStringContainsString('LoginContext::device(', $code);
        // Log::record 支持 extra 参数
        $log = (string) file_get_contents($this->root() . '/app/Models/Log.php');
        $this->assertStringContainsString("array \$extra = []", $log);
        $this->assertStringContainsString("\$extra['location']", $log);
        $this->assertStringContainsString("\$extra['device']", $log);
    }

    public function test_admin_log_page_shows_location_and_device(): void
    {
        $view = (string) file_get_contents($this->root() . '/resources/views/admin/logs.blade.php');
        $this->assertStringContainsString('地区 / IP / 设备', $view);
        $this->assertStringContainsString("\$log['location']", $view);
        $this->assertStringContainsString("\$log['device']", $view);
        $log = (string) file_get_contents($this->root() . '/app/Models/Log.php');
        $this->assertStringContainsString('l.location LIKE :kw', $log);
        $this->assertStringContainsString('l.device LIKE :kw', $log);
    }
}
