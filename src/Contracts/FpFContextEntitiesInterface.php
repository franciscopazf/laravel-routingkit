<?php

namespace FpF\RoutingKit\Contracts;

use FpF\RoutingKit\Contracts\FpFEntityInterface;
use FpF\RoutingKit\Contracts\FpFDataRepositoryInterface;
use Illuminate\Support\Collection;


interface FpFContextEntitiesInterface
{   
    public static function make(string $id, FpFDataRepositoryInterface $fpfRepository): self;

    // public function rewriteAllRoutes(?Collection $routes = null): void;

    // public function addRoute(FpFEntityInterface $route, string|FpFEntityInterface|null $parent): void;

    // public function removeRoute(string|FpFEntityInterface $routeId): void;

    // public function getAllRoutes(): Collection;

    // public function getAllFlattenedRoutes(?Collection $routes = null): Collection;

    // public function findRoute(string $routeId): ?FpFEntityInterface;

    // public function findByParamName(string $paramName, string $value): ?Collection;

    // public function findByRouteName(string $routeName): ?FpFEntityInterface;

    // public function getBreadcrumbs(string|FpFEntityInterface $routeId): Collection;

    // public function exists(string $routeId): bool;

    // public function moveRoute(FpFEntityInterface $fromRoute, FpFEntityInterface $toRoute): void;
}