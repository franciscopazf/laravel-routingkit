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
    
    public static function registerFullRoute(
        FullRoute $route,
        $prefix = '',
        $name = '',
        $parentName = '',
        array $parentMiddleware = []
    ) {
        $fullUrl = trim($route->url, '/');
        $fullName = $route->urlName;
        $middleware = array_merge($parentMiddleware, $route->urlMiddleware ?? []);
        $hasChildren = !empty($route->childrens);

        $method = strtolower($route->urlMethod);

        // Detectar si es un componente Livewire (ruta directa a clase)
        if ($route->urlAction === 'livewire') {
            Route::match([$method], '/' . $fullUrl, $route->urlController)
                ->name($fullName)
                ->middleware($middleware);
        } else {
            Route::match([$method], '/' . $fullUrl, [
                $route->urlController,
                $route->urlAction
            ])
                ->name($fullName)
                ->middleware($middleware);
        }

        // Registrar rutas hijas si existen
        if ($hasChildren) {
            Route::prefix($fullUrl)
                ->middleware($route->urlMiddleware ?? [])
                ->name($fullName)
                ->group(function () use ($route, $fullUrl, $fullName, $middleware) {
                    foreach ($route->childrens as $childRoute) {
                        if ($childRoute instanceof FullRoute) {
                            static::registerFullRoute(
                                $childRoute,
                                '/' . $fullUrl,
                                $fullName,
                                $middleware
                            );
                        }
                    }
                });
        }
    }
}
