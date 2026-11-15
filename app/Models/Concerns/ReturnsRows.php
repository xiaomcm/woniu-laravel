<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * 查询结果统一转成数组。
 *
 *既有的模型方法一律返回行数组，模板与控制器里写的是 $image['sha1'] 这种取法。
 * 2.0 底层换成 Eloquent 之后，对外仍然返回数组，调用方一个字都不用改。
 */
trait ReturnsRows
{
    protected static function row(?Model $model): ?array
    {
        return $model === null ? null : $model->toArray();
    }

    /**
     * 批量取行并转数组。
     */
    protected static function rows($models): array
    {
        $result = [];
        foreach ($models as $model) {
            $result[] = $model->toArray();
        }
        return $result;
    }
}
