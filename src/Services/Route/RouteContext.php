<?php

namespace Fp\FullRoute\Services\Route;

use Fp\FullRoute\Contracts\RouteStrategyInterface;
use Fp\FullRoute\Contracts\FpEntityInterface;

use Illuminate\Support\Collection;

/**
 * RouteContext es una clase que utiliza el patrón Strategy para manejar diferentes estrategias de rutas.
 * Permite cambiar la estrategia de rutas en tiempo de ejecución.
 */
class RouteContext implements RouteStrategyInterface
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

    public function addRoute(FpEntityInterface $entity, string|FpEntityInterface|null $parent): void
    {
        $this->strategy->addRoute($entity, $parent);
    }

    public function getBreadcrumbs(string|FpEntityInterface $entityId): Collection
    {
        return $this->strategy->getBreadcrumbs($entityId);
    }

    public function getAllRoutes(): Collection
    {
        return $this->strategy->getAllRoutes();
    }

    public function findRoute(string $entityId): ?FpEntityInterface
    {
        return $this->strategy->findRoute($entityId);
    }

    public function findByRouteName(string $entityName): ?FpEntityInterface
    {
        return $this->strategy->findByRouteName($entityName);
    }

    public function findByParamName(string $paramName, string $value): Collection
    {
        return $this->strategy->findByParamName($paramName, $value);
    }

    public function moveRoute(FpEntityInterface $fromRoute, FpEntityInterface $toRoute): void
    {
        $this->strategy->moveRoute($fromRoute, $toRoute);
    }

    public function removeRoute(string|FpEntityInterface $entityId): void
    {
        $this->strategy->removeRoute($entityId);
    }

    public function getAllFlattenedRoutes(?Collection $entitys = null): Collection
    {
        return $this->strategy->getAllFlattenedRoutes($entitys);
    }

    public function exists(string $entityId): bool
    {
        return $this->strategy->exists($entityId);
    }

    public function rewriteAllRoutes(?Collection $entitys= null ): void
    {
       
        $this->strategy->rewriteAllRoutes($entitys);
    }
}
