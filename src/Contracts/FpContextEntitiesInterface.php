<?php

namespace Fp\RoutingKit\Contracts;

use Fp\RoutingKit\Contracts\FpEntityInterface;
use Fp\RoutingKit\Contracts\FpDataRepositoryInterface;
use Illuminate\Support\Collection;


interface FpContextEntitiesInterface
{   
    public static function make(string $id, FpDataRepositoryInterface $fpRepository): self;

    // public function rewriteAllRoutes(?Collection $routes = null): void;

    // public function addRoute(FpEntityInterface $route, string|FpEntityInterface|null $parent): void;

    // public function removeRoute(string|FpEntityInterface $routeId): void;

    // public function getAllRoutes(): Collection;

    // public function getAllFlattenedRoutes(?Collection $routes = null): Collection;

    // public function findRoute(string $routeId): ?FpEntityInterface;

    // public function findByParamName(string $paramName, string $value): ?Collection;

    // public function findByRouteName(string $routeName): ?FpEntityInterface;

    // public function getBreadcrumbs(string|FpEntityInterface $routeId): Collection;

    // public function exists(string $routeId): bool;

    // public function moveRoute(FpEntityInterface $fromRoute, FpEntityInterface $toRoute): void;
}