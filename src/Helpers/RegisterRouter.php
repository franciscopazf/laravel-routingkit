<?php

namespace Fp\FullRoute\Helpers;

use Illuminate\Support\Facades\Route;
use Fp\FullRoute\Entities\FpRoute;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Registra una ruta y sus hijos de forma recursiva
 * @param FullRoute $route
 */
class RegisterRouter
{
    public static function registerRoutes(array|Collection|null $routes = null)
    {
        if (is_null($routes)) {
            $routes = FpRoute::all();
        }
        $routes->each(function ($route) {
            if ($route instanceof FpRoute)
                static::registerFullRoute($route);
        });
    }

    public static function registerFullRoute(FpRoute $route)
    {
        //echo "Registering route: {$route->id} ({$route->urlMethod})\n";
        $hasChildren = !empty($route->childrens);
        $method = strtolower($route->urlMethod);
        $isLivewire = $route->urlAction === 'livewire';
        $url = '/' . ltrim($route->getUrl(), '/');


        $middleware = $route->urlMiddleware ?? [];

        // si existe el permiso de la ruta entonces se agrega al middleware
        if ($route->accessPermission)
            $middleware[] = 'permission:' . $route->accessPermission;


        // Ruta simple
        Route::match(
            [$method],
            $url,
            $isLivewire ? $route->urlController :
                [$route->urlController, $route->urlAction]
        )->name($route->id)
            ->middleware($middleware);


        // Registrar rutas hijas dentro del grupo con middleware del padre
        if ($hasChildren)
            Route::middleware($middleware)->group(function () use ($route) {
                foreach ($route->childrens as $childRoute)
                    if ($childRoute instanceof FpRoute)
                        static::registerFullRoute($childRoute);
            });
    }
}
