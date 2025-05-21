<?php

namespace Fp\FullRoute\Services;

use Fp\FullRoute\Contracts\RouteStrategyInterface;

use Fp\FullRoute\Services\RouteFileManager;
use Fp\FullRoute\Services\RouteStrategyFile;
use Fp\FullRoute\Services\RouteValidatonService;
use Fp\FullRoute\Services\RouteContext;

class RouteStrategyFactory
{
    public static function make(string $type): RouteContext
    {
        return match ($type) {
            'file' => self::makeFileStrategy(),
            'database' => self::makeDatabaseStrategy(),
            default => throw new \InvalidArgumentException("Estrategia no válida: $type"),
        };
    }

    // si el caso es un tipo de estrategia file orquestarlo en esta funcion
    public static function makeFileStrategy(): RouteContext
    {
        // si la estrategia es tipo file entonces orquestar la estrategia correspondiente
        $routeContentManager = new RouteContentManager(); // se usa el path por defacto
        // si la estrategia es tipo file entonces orquestar la estrategia correspondiente
        $routeStrategy = new RouteStrategyFile($routeContentManager);
        // si la estrategia es tipo file entonces orquestar la estrategia correspondiente
        return RouteContext::make($routeStrategy);
    }

    // aun no esta el soporte para la estrategia de base de datos pero se puede hacer la funcion
    public static function makeDatabaseStrategy(): RouteContext
    {
        dd("Estrategia de base de datos no soportada... por ahora");
    }
}
