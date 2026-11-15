<?php
/**
 * 本地开发服务器入口（php -S 用）。
 * 静态文件直接返回，其余交给 public/index.php。
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    return false;
}

require_once __DIR__ . '/public/index.php';
