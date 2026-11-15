<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 业务模型基座。
 *
 * 三个刻意的设计：
 * 1) 关闭 Eloquent 的自动时间戳 ——既有的 created_at/updated_at 是业务层手写的，
 *    字段值必须保持 'Y-m-d H:i:s' 原样。
 * 2) 不设批量赋值白名单 ——原先是裸 SQL，没有这一层限制，这里保持同样的宽松度，
 *    字段安全由各模型自己的方法负责。
 * 3) 对外静态方法仍返回行数组（既有的契约），见 Concerns\ReturnsRows。
 */
abstract class BaseModel extends Model
{
    /**
     * 时间戳由业务层自己维护。
     */
    public $timestamps = false;

    /**
     *原先没有批量赋值保护，这里保持同样的策略。
     */
    protected $guarded = [];

    use Concerns\ReturnsRows;
}
