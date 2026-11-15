<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * 每次测试前把应用状态清干净：
     * - Setting / Auth 的静态缓存（都是进程内单例，跨测试会串）
     */
    protected function setUp(): void
    {
        parent::setUp();

        \App\Core\Setting::reset();
        \App\Core\Auth::reset();
    }
}
