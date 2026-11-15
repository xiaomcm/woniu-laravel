<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 *
 * 合并后的全站配置，供 config('woniu.*') 读取。
 * 真正的合并逻辑在 bootstrap/woniu.php，这里只做转发。
 */

declare(strict_types=1);

return require dirname(__DIR__) . '/bootstrap/woniu.php';
