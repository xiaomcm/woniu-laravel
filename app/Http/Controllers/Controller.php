<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use Illuminate\Http\Response as HttpResponse;

/**
 * 控制器基类。
 *
 *既有的 view() 是 echo 出去，这里改成返回响应对象 —— Laravel 里控制器方法的
 * 返回值就是 HTTP 响应，动作方法的返回值语义因此完全一致。
 *
 * api() / json() 内部抛响应异常（原先是 exit），效果一样：立刻中断本次请求。
 */
abstract class Controller
{
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/app'): HttpResponse
    {
        return response(View::render($view, $data, $layout));
    }

    protected function json($data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    protected function api(bool $status, string $message = '', $data = null, int $httpStatus = 200): never
    {
        Response::api($status, $message, $data, $httpStatus);
    }

    protected function validateCsrf(): void
    {
        Csrf::enforce();
    }

    protected function ids(): array
    {
        $ids = \App\Core\Request::input('ids', []);
        if (!is_array($ids)) {
            $single = \App\Core\Request::int('id');
            $ids = $single > 0 ? [$single] : [];
        }
        $result = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $result[] = $id;
            }
        }
        return array_values(array_unique($result));
    }
}
