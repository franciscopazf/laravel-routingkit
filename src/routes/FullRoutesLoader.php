<?php

use Illuminate\Support\Facades\Route;
use Fp\FullRoute\Clases\FullRoute;

/**
 * Registra una ruta y sus hijos de forma recursiva
 * @param FullRoute $route
 */
function registerFullRoute(
    FullRoute $route,
    $prefix = '',
    $name = '',
    $parentName = '',
    array $parentMiddleware = []
) {
    // Limpiar doble slash y espacios
    $fullUrl = $route->url;
    $fullName = $route->urlName;

    $middleware = array_merge($parentMiddleware, $route->urlMiddleware ?? []);

    $hasChildren = !empty($route->childrens);

    // Registrar la ruta actual
    Route::match([strtolower($route->urlMethod)], '/' . $fullUrl, [
        $route->urlController,
        $route->urlAction
    ])
        ->name($fullName)
        ->middleware($middleware);

    // Si tiene hijos, agrupar sin repetir el prefix
    if ($hasChildren) {
        Route::prefix($fullUrl)
            ->middleware($route->urlMiddleware ?? [])
            ->name($fullName)
            ->group(function () use ($route, $fullUrl, $fullName, $middleware) {
                foreach ($route->childrens as $childRoute) {
                    if ($childRoute instanceof FullRoute) {
                        registerFullRoute(
                            $childRoute,
                            '/' .
                            $fullUrl,
                            $fullName,
                            $middleware
                        );
                    }
                }
            });
    }
}



$fullRoutes = require base_path('config/fullroute_config.php');

foreach ($fullRoutes as $routeDef) {
    if (!$routeDef instanceof FullRoute) continue;

    registerFullRoute($routeDef);
}
