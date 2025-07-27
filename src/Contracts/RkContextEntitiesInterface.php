<?php

namespace Rk\RoutingKit\Contracts;

use Rk\RoutingKit\Contracts\RkEntityInterface;
use Rk\RoutingKit\Contracts\RkDataRepositoryInterface;
use Illuminate\Support\Collection;


interface RkContextEntitiesInterface
{   
    public static function make(string $id, RkDataRepositoryInterface $rkRepository): self;

    // public function rewriteAllRoutes(?Collection $routes = null): void;

    // public function addRoute(RkEntityInterface $route, string|RkEntityInterface|null $parent): void;

    // public function removeRoute(string|RkEntityInterface $routeId): void;

    // public function getAllRoutes(): Collection;

    // public function getAllFlattenedRoutes(?Collection $routes = null): Collection;

    // public function findRoute(string $routeId): ?RkEntityInterface;

    // public function findByParamName(string $paramName, string $value): ?Collection;

    // public function findByRouteName(string $routeName): ?RkEntityInterface;

    // public function getBreadcrumbs(string|RkEntityInterface $routeId): Collection;

    // public function exists(string $routeId): bool;

    // public function moveRoute(RkEntityInterface $fromRoute, RkEntityInterface $toRoute): void;
}