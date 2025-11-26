<?php

use Kmin\View;
use support\Response;

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
