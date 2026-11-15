<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Core;

/**
 * 视图渲染。
 *
 *原先是 include 一个 PHP 模板再套一层布局模板，2.0 换成 Blade，
 * 但「先渲染内容页、再把内容塞进布局」的结构保持不变，
 * 控制器里 $this->view('images', [...], 'layouts/site') 的调用方式不用改。
 */
class View
{
    private static array $shared = [];

    public static function share(array $data): void
    {
        self::$shared = array_merge(self::$shared, $data);
    }

    public static function exists(string $view): bool
    {
        return view()->exists($view);
    }

    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $shared = self::$shared;
        if (!array_key_exists('flashes', $shared)) {
            $shared['flashes'] = flashes();
        }
        if (!array_key_exists('me', $shared)) {
            $shared['me'] = Auth::user();
        }
        if (!array_key_exists('siteSettings', $shared)) {
            $shared['siteSettings'] = Setting::all();
        }
        if (!array_key_exists('currentPath', $shared)) {
            $shared['currentPath'] = Request::path();
        }

        $vars = array_merge($shared, $data);

        $content = (string) view($view, $vars)->render();
        if ($layout === null) {
            return $content;
        }
        return (string) view($layout, array_merge($vars, ['content' => $content]))->render();
    }
}
