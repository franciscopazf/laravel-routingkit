<?php

namespace FP\RoutingKit\Routes;

use Illuminate\Support\Facades\Route;
use FP\RoutingKit\Entities\FPRoute;
use Illuminate\Support\Collection;


/**
 * Registra una ruta y sus hijos de forma recursiva
 * @param RoutingKit $route
 */
class FPRegisterRouter
{
    public static function registerRoutes(array|Collection|null $routes = null)
    {
        if (is_null($routes)) {
            $routes = FPRoute::all();
        }

        $routes->each(function ($route) {
            if ($route instanceof FPRoute) {
                static::registerRoutingKit($route);
            }
        });
    }

    public static function registerRoutingKit(FPRoute $route, string $accumulatedPrefix = '')
    {
        $hasItems = !empty($route->items);
        $method = strtolower($route->urlMethod ?? 'get');

        // Prefijo local y acumulado, sin barras sobrantes
        $localPrefix = trim($route->getPrefix(), '/');
        $fullPrefix = trim(implode('/', array_filter([$accumulatedPrefix, $localPrefix])), '/');

        // Construcción segura de la URL final evitando dobles slashes
        $parts = [
            trim($fullPrefix, '/'),
            trim($route->getUrl() ?: '', '/'),
        ];
        $finalUrl = '/' . implode('/', array_filter($parts));

        $middleware = $route->urlMiddleware ?? [];
        if ($route->accessPermission) {
            $middleware[] = 'permission:' . $route->accessPermission;
        }

        // Si no es grupo puro y tiene URL, registra su propia ruta
        if (!$route->isGroup && $route->getUrl()) {
            Route::match([$method], $finalUrl, $route->urlController)
                ->name($route->id)
                ->middleware($middleware);
        }

        // Si tiene hijos o es grupo, registra grupo para rutas hijas con prefijo local
        if ($hasItems || $route->isGroup) {
            Route::prefix($localPrefix)
                ->middleware($middleware)
                ->group(function () use ($route, $fullPrefix) {
                    foreach ($route->items as $childRoute) {
                        if ($childRoute instanceof FPRoute) {
                            static::registerRoutingKit($childRoute, $fullPrefix);
                        }
                    }
                });
        }
    }
}
