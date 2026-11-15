<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

namespace App\Routing;

use Illuminate\Routing\UrlGenerator;

/**
 * URL 生成器。
 *
 *既有的 url() 与 asset() 是自己写的：
 *   url($path)   → base_url() . '/' . ltrim($path, '/')
 *   asset($path) → url('assets/' . $path) . '?v=' . 文件修改时间
 *
 * Laravel 自带的 url() / asset() 是全局函数，最终都落到容器里的 UrlGenerator。
 * 把这两个方法在这里覆写掉，全局函数无论由谁定义（加载顺序取决于入口文件），
 * 产出的地址都与既有行为一致。
 *
 * 这样做的好处：语义只存在一处，不依赖「谁先 require」的加载顺序。
 */
class WoniuUrlGenerator extends UrlGenerator
{
    /**
     * 生成站内地址。
     *
     * 与既有的 url() 一致：站点根地址 + '/' + 去掉开头斜杠的路径。
     * 站点根地址取安装时写入的 app.url（与既有的 base_url() 同源）。
     */
    public function to($path, $extra = [], $secure = null)
    {
        if ($this->isValidUrl($path)) {
            return $path;
        }

        $tail = implode('/', array_map('rawurlencode', (array) $this->formatParameters($extra)));

        [$path, $query] = $this->extractQueryString($path);

        return \App\Core\Url::to($path, $tail) . $query;
    }

    /**
     * 生成静态资源地址。
     *
     * 与既有的 asset() 一致：走 assets/ 目录，并带上文件修改时间做版本号，
     * 浏览器不会拿旧缓存。
     */
    public function asset($path, $secure = null)
    {
        if ($this->isValidUrl($path)) {
            return $path;
        }

        $file = BASE_PATH . '/public/assets/' . ltrim((string) $path, '/');
        $ver = is_file($file)
            ? (string) filemtime($file)
            : (string) \App\Core\Config::get('app.version', '1');

        return \App\Core\Url::to('assets/' . ltrim((string) $path, '/')) . '?v=' . rawurlencode($ver);
    }
}
