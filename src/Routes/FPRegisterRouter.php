<?php

namespace FP\RoutingKit\Routes;

use Illuminate\Support\Facades\Route;
use FP\RoutingKit\Entities\FPRoute;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Collection;

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

    public static function registerRoutingKit(FPRoute $route): RoutingRoute|null
    {
        $hasItems = $route->getItems()->isNotEmpty();
        $method = strtolower($route->urlMethod ?? 'get');

        // Solo URL relativa; prefix se aplicará automáticamente si está en grupo
        $finalUrl = '/' . trim($route->getUrl() ?: '', '/');

        // Middleware individual
        $middleware = $route->urlMiddleware ?? [];
        if ($route->accessPermission) {
            $middleware[] = 'permission:' . $route->accessPermission;
        }

        // Registrar ruta simple
        if (!$route->isGroup && $route->getUrl()) {
            return Route::match([$method], $finalUrl, $route->urlController)
                ->name($route->id)
                ->middleware($middleware);

        }

        // Registrar grupo
        if ($hasItems || $route->isGroup) {
            Route::prefix(trim($route->getPrefix(), '/')) 
                ->middleware($middleware)
                ->group(function () use ($route) {
                    foreach ($route->getItems() as $childRoute) {
                        if ($childRoute instanceof FPRoute) {
                            static::registerRoutingKit($childRoute);
                        }
                    }
                });
        }

        return null;
    }
}
