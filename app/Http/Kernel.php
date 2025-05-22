<?php


namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middleware = [
        // Other global middleware...
        \App\Http\Middleware\SetLocaleMiddleware::class,
    ];

    protected $middlewareGroups = [
        'web' => [
            // Other web middleware...
//            \App\Http\Middleware\SetLocaleMiddleware::class,
        ],
        'api' => [
            // API middleware...
        ],
    ];

    protected $routeMiddleware = [
        // Other route middleware...
    ];
}
