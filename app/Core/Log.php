<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Core;

class Log
{
    public static function error(\Throwable $e, string $channel = 'app'): void
    {
        $line = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        self::write($channel, $line);
    }

    public static function info(string $message, string $channel = 'app'): void
    {
        self::write($channel, sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message));
    }

    private static function write(string $channel, string $line): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/' . $channel . '-' . date('Y-m-d') . '.log';
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
