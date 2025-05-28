<?php

namespace Fp\FullRoute\Contracts;

use Fp\FullRoute\Clases\FullRoute;
use Fp\FullRoute\Services\Route\Strategies\RouteContentManager;
use Fp\FullRoute\Services\Transformer\TransformerContext;
use Illuminate\Support\Collection;

interface RouteStrategyInterface
{
    public static function make(RouteContentManager $fileManager, ?TransformerContext $transformer = null): self;

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

    public function rewriteAllRoutes(?Collection $routes): void;
    
}
