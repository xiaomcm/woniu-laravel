<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

declare(strict_types=1);

return [
    'app' => [
        'name'      => '蜗牛图床',
        'url'       => '',

        'key'       => '',

        'debug'     => true,
        'timezone'  => 'Asia/Shanghai',
        'locale'    => 'zh_CN',
        'version'   => '2.0.0',
    ],

    'db' => [
        'driver'      => 'mysql',

        'host'        => '127.0.0.1',
        'port'        => 3306,
        'database'    => 'woniu',
        'username'    => 'root',
        'password'    => '',
        'charset'     => 'utf8mb4',
        'prefix'      => '',
        'sqlite_path' => 'storage/database.sqlite',
    ],

    'session' => [
        'name'     => 'woniu_session',
        'lifetime' => 86400,
    ],

    'upload' => [
        'driver_dir' => 'storage/uploads',

        'tmp_dir'    => 'storage/tmp',
    ],
];
