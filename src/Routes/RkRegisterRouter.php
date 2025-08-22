<?php

namespace Rk\RoutingKit\Routes;

use Illuminate\Support\Facades\Route;
use Rk\RoutingKit\Entities\RkRoute;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Collection;

class RkRegisterRouter
{
    public static function registerRoutes(array|Collection|null $routes = null, array $parentDomains = []): void
    {
        if (is_null($routes)) {
            $routes = RkRoute::all();
        }

        $routes->each(function ($route) use ($parentDomains) {
            if ($route instanceof RkRoute) {
                static::registerRoutingKit($route, $parentDomains);
            }
        });
    }

    public static function registerRoutingKit(RkRoute $route, array $parentDomains = []): RoutingRoute|null
    {
        $hasItems = $route->getItems()->isNotEmpty();
        $method = strtolower($route->urlMethod ?? 'get');

        $finalUrl = '/' . trim($route->getUrl() ?: '', '/');

        $middleware = $route->urlMiddleware ?? [];
        if ($route->accessPermission) {
            $middleware[] = 'permission:' . $route->accessPermission;
        }

        // Determinar dominios heredados o propios
        $domains = $route->getDomains();
        if (empty($domains) && !empty($parentDomains)) {
            $domains = $parentDomains;
        }

        // Si no hay dominios, se registra normal
        if (empty($domains)) {
            return static::registerWithoutDomains($route, $middleware, $finalUrl, $hasItems);
        }

        // Registrar para cada dominio
        foreach ($domains as $domain) {
            Route::domain($domain)
                ->middleware($middleware)
                ->prefix(trim($route->getPrefix(), '/'))
                ->group(function () use ($route, $hasItems, $middleware, $finalUrl, $domain) {
                    if (!$route->isGroup && $route->getUrl()) {
                        Route::match([strtolower($route->urlMethod)], $finalUrl, $route->urlController)
                            ->name($route->id)
                            ->middleware($middleware);
                    }

                    if ($hasItems) {
                        foreach ($route->getItems() as $childRoute) {
                            if ($childRoute instanceof RkRoute) {
                                static::registerRoutingKit($childRoute, [$domain]);
                            }
                        }
                    }
                });
        }

        return null;
    }

    protected static function registerWithoutDomains(RkRoute $route, array $middleware, string $finalUrl, bool $hasItems): RoutingRoute|null
    {
        if (!$route->isGroup && $route->getUrl()) {
            return Route::match([strtolower($route->urlMethod)], $finalUrl, $route->urlController)
                ->name($route->id)
                ->middleware($middleware);
        }

        if ($hasItems || $route->isGroup) {
            Route::prefix(trim($route->getPrefix(), '/'))
                ->middleware($middleware)
                ->group(function () use ($route) {
                    foreach ($route->getItems() as $childRoute) {
                        if ($childRoute instanceof RkRoute) {
                            static::registerRoutingKit($childRoute);
                        }
                    }
                });
        }

        return null;
    }
}
