<?php

namespace FPJ\RoutingKit\Routes;

use Illuminate\Support\Facades\Route;
use FPJ\RoutingKit\Entities\FPJRoute;
use Illuminate\Support\Collection;


/**
 * Registra una ruta y sus hijos de forma recursiva
 * @param RoutingKit $route
 */
class FPJRegisterRouter
{
    public static function registerRoutes(array|Collection|null $routes = null)
    {
        if (is_null($routes)) {
            $routes = FPJRoute::all();
        }
        $routes->each(function ($route) {
            if ($route instanceof FPJRoute)
                static::registerRoutingKit($route);
        });
    }

    public static function registerRoutingKit(FPJRoute $route)
    {
        //echo "Registering route: {$route->id} ({$route->urlMethod})\n";
        $hasItems = !empty($route->items);
        $method = strtolower($route->urlMethod);
        $url = '/' . ltrim($route->getUrl(), '/');


        $middleware = $route->urlMiddleware ?? [];

        // si existe el permiso de la ruta entonces se agrega al middleware
        if ($route->accessPermission)
            $middleware[] = 'permission:' . $route->accessPermission;


        // Ruta simple
        if (!$route->isGroup) {
            Route::match(
                [$method],
                $url,
                $route->urlController
            )->name($route->id)
                ->middleware($middleware);
        }


        // Registrar rutas hijas dentro del grupo con middleware del padre
        if ($hasItems)
            Route::middleware($middleware)->group(function () use ($route) {
                foreach ($route->items as $childRoute)
                    if ($childRoute instanceof FPJRoute)
                        static::registerRoutingKit($childRoute);
            });
    }


}
