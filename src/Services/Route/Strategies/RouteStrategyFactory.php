<?php

namespace Fp\FullRoute\Services\Route\Strategies;

use Fp\FullRoute\Contracts\RouteStrategyInterface;
use Fp\FullRoute\Services\Route\RouteContext;
use Fp\FullRoute\Services\Route\Strategies\ArrayFileRouteStrategy;
use Fp\FullRoute\Services\Route\Strategies\TreeFileRouteStrategy;
use Fp\FullRoute\Services\Route\Strategies\RouteContentManager;

class RouteStrategyFactory
{
    private function __construct() {} // Prevent instantiation

    /**
     * Crea una instancia de RouteContext según el tipo de estrategia.
     */
    public static function make(
        string $type,
        ?string $filePath = null,
        bool $onlyStringSupport = true
    ): RouteContext {
        $strategy = self::buildStrategy($type, $filePath, $onlyStringSupport);
        return RouteContext::make($strategy);
    }

    /**
     * Devuelve una estrategia individual sin envolver en un RouteContext.
     */
    public static function buildStrategy(
        string $type,
        ?string $filePath = null,
        bool $onlyStringSupport = true
    ): RouteStrategyInterface {
        return match ($type) {
            'file_array' => self::buildFileArrayStrategy($filePath, $onlyStringSupport),
            'file_tree'  => self::buildFileTreeStrategy($filePath, $onlyStringSupport),
            'database'   => self::buildDatabaseStrategy(),
            default      => throw new \InvalidArgumentException("Estrategia no válida: $type"),
        };
    }

    public static function buildFileArrayStrategy(
        ?string $filePath = null,
        bool $onlyStringSupport = true
    ): ArrayFileRouteStrategy {
        $routeContentManager = new RouteContentManager($filePath, $onlyStringSupport);
        return new ArrayFileRouteStrategy($routeContentManager);
    }

    public static function buildFileTreeStrategy(
        ?string $filePath = null,
        bool $onlyStringSupport = true
    ): TreeFileRouteStrategy {
        $routeContentManager = new RouteContentManager($filePath, $onlyStringSupport);
        return new TreeFileRouteStrategy($routeContentManager);
    }

    public static function buildDatabaseStrategy(): RouteStrategyInterface
    {
        //W
        throw new \RuntimeException("Estrategia de base de datos no implementada aún.");
    }
}
