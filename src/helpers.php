<?php

use Kmin\View;
use support\Response;
use Webman\Route;
use Kmin\Template;

if (!function_exists('km_assign')) {
    /**
     * 模板变量赋值
     *
     * @param string|array $name 变量名
     * @param mixed $value 变量值
     * @return void
     */
    function km_assign(string|array $name, mixed $value = null): void
    {
        View::assign($name, $value);
    }
}

if (!function_exists('km_view')) {
    /**
     * kmin view response
     *
     * @param mixed $template 模板文件名
     * @param array $vars 模板变量
     * @param string|null $app 应用名称
     * @param string|null $plugin 插件名称
     * @return Response
     */
    function km_view(
        mixed $template = null,
        array $vars = [],
        ?string $app = null,
        ?string $plugin = null
    ): Response {
        return new Response(
            200,
            [], //js
            View::render(...template_inputs($template, $vars, $app, $plugin))
        );
    }
}

if (!function_exists('km_component')) {
    /**
     * kmin component response
     *
     * @param string $key 路由前缀
     * @param string $value 组件路径
     * @return void
     */
    function km_component($key, $value): void
    {
        Route::any("$key/{path:.+}", function ($request, string $path) use ($value) {
            $file = $value . DIRECTORY_SEPARATOR . $path;
            if (file_exists($file) && pathinfo($file, PATHINFO_EXTENSION) == "php") {
                $options = [
                    'view_path' => $value,
                    'cache_path' => runtime_path() . '/views/component/',
                    'view_suffix' => 'php',
                ];
                $views = new Template($options);
                if (isset(request()->_view_vars)) {
                    $vars = (array)request()->_view_vars;
                } else {
                    $vars = [];
                }
                $template = $views->fetch(str_replace(".php", "", $path), $vars);
                return new Response(200, ['Content-Type' => 'text/javascript'], $template);
            } else {
                return new Response(404, [], '404 Not Found');
            }
        });
    }
}
