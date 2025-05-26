<?php

namespace Fp\FullRoute\Services\Route\Strategies;

use Fp\FullRoute\Services\Route\RouteContext;
use Fp\FullRoute\Services\Route\Strategies\ArrayFileRouteStrategy;
use Fp\FullRoute\Services\Route\Strategies\TreeFileRouteStrategy;
use Fp\FullRoute\Services\Route\Strategies\RouteContentManager;


class RouteStrategyFactory
{
    private function __construct()
    {
        // Constructor privado para evitar instanciación directa
    }
    /**
     * Crea una instancia de RouteContext según el tipo de estrategia.
     *
     * @param string $type El tipo de estrategia ('file_array', 'file_tree', 'database').
     * @param string|null $filePath La ruta del archivo, si es necesario.
     * @return RouteContext
     * @throws \InvalidArgumentException Si el tipo de estrategia no es válido.
     */
    public static function make(string $type, ?string $filePath = null): RouteContext
    {
        return match ($type) {
            'file_array' => self::makeFileArrayStrategy($filePath),
            'file_tree' => self::makeFileTreeStrategy($filePath),
            'database' => self::makeDatabaseStrategy(),
            default => throw new \InvalidArgumentException("Estrategia no válida: $type"),
        };
    }


    /**
     * Crea una instancia de RouteContext con la estrategia de archivo tipo array.
     *
     * @param string|null $filePath La ruta del archivo, si es necesario.
     * @return RouteContext
     */
    public static function makeFileArrayStrategy(?string $filePath = null): RouteContext
    {

        // si la estrategia es tipo file entonces orquestar la estrategia correspondiente
        $routeContentManager = new RouteContentManager($filePath); // se usa el path por defacto
        // pero para test se puede pasar el path de test        
        // si la estrategia es tipo file entonces orquestar la estrategia correspondiente
        $routeStrategy = new ArrayFileRouteStrategy($routeContentManager);
        // si la estrategia es tipo file entonces orquestar la estrategia correspondiente
        return RouteContext::make($routeStrategy);
    }

    /**
     * Crea una instancia de RouteContext con la estrategia de archivo tipo árbol.
     *
     * @param string|null $filePath La ruta del archivo, si es necesario.
     * @return RouteContext
     */
    public static function makeFileTreeStrategy(?string $filePath = null): RouteContext
    {
       
        // si la estrategia es tipo file_unit entonces orquestar la estrategia correspondiente
        $routeContentManager = new RouteContentManager($filePath); // se usa el path por defacto
        
        // pero para test se puede pasar el path de test        
        // si la estrategia es tipo file_unit entonces orquestar la estrategia correspondiente
        $routeStrategy = new TreeFileRouteStrategy($routeContentManager);
        // si la estrategia es tipo file_unit entonces orquestar la estrategia correspondiente
        return RouteContext::make($routeStrategy);
    }

    /**
     * Crea una instancia de RouteContext con la estrategia de base de datos.
     *
     * @return RouteContext
     */
    public static function makeDatabaseStrategy(): RouteContext
    {
        dd("Estrategia de base de datos no soportada... por ahora");
    }
}
