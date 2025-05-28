<?php

namespace Fp\FullRoute\Services\Route;

use Fp\FullRoute\Contracts\RouteStrategyInterface;
use Fp\FullRoute\Clases\FullRoute;
use Illuminate\Support\Collection;

/**
 * RouteContext es una clase que utiliza el patrón Strategy para manejar diferentes estrategias de rutas.
 * Permite cambiar la estrategia de rutas en tiempo de ejecución.
 */
class RouteContext
{
    protected RouteStrategyInterface $strategy;

    public function __construct(RouteStrategyInterface $strategy)
    {
        $this->strategy = $strategy;
    }

    public static function make(RouteStrategyInterface $strategy): RouteContext
    {
        return new self($strategy);
    }

    public function setStrategy(RouteStrategyInterface $strategy): void
    {
        $this->strategy = $strategy;
    }

    public function getStrategy(): RouteStrategyInterface
    {
        return $this->strategy;
    }

    public function addRoute(FullRoute $route, string|FullRoute|null $parent): void
    {
        $this->strategy->addRoute($route, $parent);
    }

    public function getBreadcrumbs(string|FullRoute $routeId): Collection
    {
        return $this->strategy->getBreadcrumbs($routeId);
    }

    public function getAllRoutes(): Collection
    {
        return $this->strategy->getAllRoutes();
    }

    public function findRoute(string $routeId): ?FullRoute
    {
        return $this->strategy->findRoute($routeId);
    }

    public function findByRouteName(string $routeName): ?FullRoute
    {
        return $this->strategy->findByRouteName($routeName);
    }

    public function findByParamName(string $paramName, string $value): Collection
    {
        return $this->strategy->findByParamName($paramName, $value);
    }

    public function moveRoute(FullRoute $fromRoute, FullRoute $toRoute): void
    {
        $this->strategy->moveRoute($fromRoute, $toRoute);
    }

    public function removeRoute(string $routeId): void
    {
        $this->strategy->removeRoute($routeId);
    }

    public function getAllFlattenedRoutes(?Collection $routes = null): Collection
    {
        return $this->strategy->getAllFlattenedRoutes($routes);
    }

    public function exists(string $routeId): bool
    {
        return $this->strategy->exists($routeId);
    }

    public function rewriteAllRoutes(?Collection $routes= null ): void
    {
        $this->strategy->rewriteAllRoutes($routes);
    }
}
