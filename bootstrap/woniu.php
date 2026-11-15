<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 *
 * 全站配置的合并入口。
 *
 * 全站配置采用「config/defaults.php 作为默认值 + config/installed.php 作为安装时覆盖」，
 * 这里原样保留这套结构：已装好的站点把 config/installed.php 拷到项目目录下就能直接跑，
 * 数据库连接、应用密钥、站点地址都不用重新填 —— 这是站点迁移时数据零丢失的前提。
 */

declare(strict_types=1);

$base = require dirname(__DIR__) . '/config/defaults.php';

$installedFile = dirname(__DIR__) . '/config/installed.php';
$installed = is_file($installedFile) ? require $installedFile : [];
if (!is_array($installed)) {
    $installed = [];
}

// installed.php 里的键是整体覆盖（不是递归合并），跟既有的 array_replace_recursive 行为一致
return array_replace_recursive($base, $installed);
