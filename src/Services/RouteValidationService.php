<?php

namespace Fp\FullRoute\Services;

use Fp\FullRoute\Clases\FullRoute;
use Illuminate\Support\Collection;

class RouteValidationService
{
    public static function validateRoute(FullRoute $route): void
    {
        self::validateIdIsNotEmpty($route);
        self::validateIdIsUnique($route);
        self::validateRouteIsNotEmpty($route);
        self::validateMethodIsValid($route);
    }

    protected static function validateIdIsNotEmpty(FullRoute $route): void
    {
        if (empty($route->getId())) {
            throw new \Exception("El ID no puede estar vacío.");
        }
    }

    protected static function validateIdIsUnique(FullRoute $route): void
    {
        $allRoutes = collect(config('fullroute_config'));

        $flattened = self::flattenRoutes($allRoutes);

        $ids = $flattened->pluck('id');

        if ($ids->contains($route->getId())) {
            throw new \Exception("El ID '{$route->getId()}' ya existe.");
        }
    }
    protected static function flattenRoutes(Collection $routes): Collection
    {
        return $routes->flatMap(function (FullRoute $route) {
            $children = collect($route->getChildrens());
            return collect([$route])->merge(self::flattenRoutes($children));
        });
    }


    protected static function validateRouteIsNotEmpty(FullRoute $route): void
    {
        if (empty($route->getUrl())) {
            throw new \Exception("La ruta no puede estar vacía.");
        }
    }

    protected static function validateMethodIsValid(FullRoute $route): void
    {
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        if (!in_array($route->getUrlMethod(), $validMethods)) {
            throw new \Exception("El método '{$route->getUrlMethod()}' no es válido.");
        }
    }
}
