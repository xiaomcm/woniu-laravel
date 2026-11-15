<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Drivers;

abstract class SecurityDriver extends Driver
{
    public function type(): string
    {
        return 'security';
    }

    /**
     * @param array $image pathname=相对路径 local_path=本地绝对路径 name=文件名 mimetype=类型 url=外链
     */
    abstract public function audit(array $image): Result;
}
