<?php

namespace FPJ\RoutingKit\Contracts;

use FPJ\RoutingKit\Contracts\FPJEntityInterface;
use FPJ\RoutingKit\Contracts\FPJDataRepositoryInterface;
use Illuminate\Support\Collection;


interface FPJContextEntitiesInterface
{   
    public static function make(string $id, FPJDataRepositoryInterface $fpjRepository): self;

    // public function rewriteAllRoutes(?Collection $routes = null): void;

    // public function addRoute(FPJEntityInterface $route, string|FPJEntityInterface|null $parent): void;

    // public function removeRoute(string|FPJEntityInterface $routeId): void;

    // public function getAllRoutes(): Collection;

    // public function getAllFlattenedRoutes(?Collection $routes = null): Collection;

    // public function findRoute(string $routeId): ?FPJEntityInterface;

    // public function findByParamName(string $paramName, string $value): ?Collection;

    // public function findByRouteName(string $routeName): ?FPJEntityInterface;

    // public function getBreadcrumbs(string|FPJEntityInterface $routeId): Collection;

    // public function exists(string $routeId): bool;

    // public function moveRoute(FPJEntityInterface $fromRoute, FPJEntityInterface $toRoute): void;
}