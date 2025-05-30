<?php

namespace Fp\FullRoute\Contracts;

use Fp\FullRoute\Contracts\FpEntityInterface;
use Fp\FullRoute\Services\Route\Strategies\RouteContentManager;
use Fp\FullRoute\Services\Transformer\TransformerContext;

use Illuminate\Support\Collection;

interface RouteStrategyInterface
{
    public static function make(RouteContentManager $fileManager, ?TransformerContext $transformer = null): self;

    public function addRoute(FpEntityInterface $route, string|FpEntityInterface|null $parent): void;

    public function getAllRoutes(): Collection;

    public function findRoute(string $routeId): ?FpEntityInterface;

    public function moveRoute(FpEntityInterface $fromRoute, FpEntityInterface $toRoute): void;

    public function removeRoute(string $routeId): void;

    public function getAllFlattenedRoutes(Collection $routes): Collection;

    public function exists(string $routeId): bool;

    public function findByRouteName(string $routeName): ?FpEntityInterface;

    public function findByParamName(string $paramName, string $value): ?Collection;

    public function getBreadcrumbs(string|FpEntityInterface $routeId): Collection;

    public function rewriteAllRoutes(?Collection $routes): void;
    
}
