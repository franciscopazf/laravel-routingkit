<?php

namespace FP\RoutingKit\Contracts;

use FP\RoutingKit\Contracts\FPEntityInterface;
use FP\RoutingKit\Contracts\FPDataRepositoryInterface;
use Illuminate\Support\Collection;


interface FPContextEntitiesInterface
{   
    public static function make(string $id, FPDataRepositoryInterface $fpRepository): self;

    // public function rewriteAllRoutes(?Collection $routes = null): void;

    // public function addRoute(FPEntityInterface $route, string|FPEntityInterface|null $parent): void;

    // public function removeRoute(string|FPEntityInterface $routeId): void;

    // public function getAllRoutes(): Collection;

    // public function getAllFlattenedRoutes(?Collection $routes = null): Collection;

    // public function findRoute(string $routeId): ?FPEntityInterface;

    // public function findByParamName(string $paramName, string $value): ?Collection;

    // public function findByRouteName(string $routeName): ?FPEntityInterface;

    // public function getBreadcrumbs(string|FPEntityInterface $routeId): Collection;

    // public function exists(string $routeId): bool;

    // public function moveRoute(FPEntityInterface $fromRoute, FPEntityInterface $toRoute): void;
}