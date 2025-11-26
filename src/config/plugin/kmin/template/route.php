<?php

use Webman\Route;
use Kmin\Template;
use support\Response;

if (config("plugin.kmin.template.app.enable")) {
    Route::any("/kmin/{path:.+}", function ($request, string $path) {
        $dir = DIRECTORY_SEPARATOR;
        $assets = base_path("vendor{$dir}kmin{$dir}template{$dir}src{$dir}assets{$dir}");
        $find = $assets . $path;
        if (file_exists($find)) {
            $content = file_get_contents($find);
            $ext = pathinfo($find, PATHINFO_EXTENSION);
            $type = '';
            switch ($ext) {
                case 'js':
                    $type = 'text/javascript';
                    break;
                case 'css':
                    $type = 'text/css';
                    break;
                default:
                    $type = 'text/' . $ext;
                    break;
            }
            return response($content, 200, [
                'Content-Type' => $type,
            ]);
        } else {
            return response('404 Not Found', 404);
        }
    });

    foreach (config("plugin.kmin.template.app.component") as $key => $value) {
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
                return response($template, 200, ['Content-Type' => 'text/javascript']);
            } else {
                return response('404 Not Found', 404);
            }
        });
    }
}
