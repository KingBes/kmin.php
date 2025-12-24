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
        km_component($key, $value);
    }
}
