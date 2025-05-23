<?php

namespace Fp\FullRoute\Helpers;

use Illuminate\Support\Facades\Route;
use Fp\FullRoute\Clases\FullRoute;
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
            $routes = FullRoute::all();
        }
        $routes->each(function ($route) {
            if ($route instanceof FullRoute) {
                static::registerFullRoute($route);
            }
        });
    }

    public static function registerFullRoute(FullRoute $route)
    {
        $hasChildren = !empty($route->childrens);
        $method = strtolower($route->urlMethod);
        $isLivewire = $route->urlAction === 'livewire';
        $url = '/' . ltrim($route->getFullUrl(), '/');
        $middleware = $route->urlMiddleware ?? [];

        // Ruta simple
        Route::match([$method],$url,
            $isLivewire ? $route->urlController : [$route->urlController, $route->urlAction]
        )->name($route->fullUrlName)
            ->middleware($middleware);


        // Registrar rutas hijas dentro del grupo con middleware del padre
        if ($hasChildren) {
            Route::middleware($middleware)->group(function () use ($route) {
                foreach ($route->childrens as $childRoute) {
                    if ($childRoute instanceof FullRoute) {
                        static::registerFullRoute($childRoute);
                    }
                }
            });
        }
    }
}
