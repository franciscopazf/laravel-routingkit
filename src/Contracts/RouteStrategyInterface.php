<?php

namespace Fp\FullRoute\Contracts;

use Fp\FullRoute\Clases\FullRoute;
use Illuminate\Support\Collection;

interface RouteStrategyInterface
{
    public static function make(
        \Fp\FullRoute\Services\RouteContentManager $fileManager
    ): self;

    public function addRoute(FullRoute $route, string|FullRoute|null $parent): void;

    public function getAllRoutes(): Collection;

    public function findRoute(string $routeId): ?FullRoute;

    public function moveRoute(FullRoute $fromRoute, FullRoute $toRoute): void;

    public function removeRoute(string $routeId): void;

    public function getAllFlattenedRoutes(Collection $routes): Collection;

    public function exists(string $routeId): bool;

    public function findByRouteName(string $routeName): ?FullRoute;
    
    public function findByParamName(string $paramName, string $value): ?Collection;
   
    public function getBreadcrumbs(string|FullRoute $routeId): Collection;
}
