<?php

namespace Fp\FullRoute\Services;

use Fp\FullRoute\Clases\FullRoute;
use Illuminate\Support\Collection;

class RouteValidationService
{
    /**
     * Validar la ruta
     *
     * @param FullRoute $route
     * @throws \Exception
     */
    public static function validateRoute(FullRoute $route): void
    {
        self::validateIdIsNotEmpty($route);
        self::validateIdIsUnique($route);
        self::validateRouteIsNotEmpty($route);
        self::validateMethodIsValid($route);
    }

    /**
     * Validar la ruta para inserción
     *
     * @param FullRoute $route
     * @throws \Exception
     */
    public static function validateInsertRoute(FullRoute $route): void
    {
        self::validateIdIsNotEmpty($route);
        self::validateRouteIsNotEmpty($route);
        self::validateMethodIsValid($route);
    }

    /**
     * Validar la eliminación de la ruta
     *
     * @param FullRoute $route
     * @throws \Exception
     */
    public static function validateDeleteRoute(FullRoute $route): void
    {
        self::validateIdIsNotEmpty($route);
        self::validateRouteIsNotEmpty($route);
        self::validateMethodIsValid($route);
    }

    /**
     * Validar el movimiento de la ruta
     *
     * @param FullRoute $route
     * @throws \Exception
     */
    public static function validateMoveRoute(FullRoute $route): void
    {
        self::validateIdIsNotEmpty($route);
        self::validateRouteIsNotEmpty($route);
        self::validateMethodIsValid($route);
    }


    /**
     * Validar que el ID de la ruta no esté vacío
     *
     * @param FullRoute $route
     * @throws \Exception
     */
    protected static function validateIdIsNotEmpty(FullRoute $route): void
    {
        if (empty($route->getId())) {
            throw new \Exception("El ID no puede estar vacío.");
        }
    }

    /**
     * Validar que el ID de la ruta sea único
     *
     * @param FullRoute $route
     * @throws \Exception
     */
    protected static function validateIdIsUnique(FullRoute $route): void
    {
        $flattened = FullRoute::allFlattened();

        $ids = $flattened->pluck('id');

        if ($ids->contains($route->getId())) {
            throw new \Exception("El ID '{$route->getId()}' ya existe.");
        }
    }


    /**
     * Validar que la ruta no esté vacía
     *
     * @param FullRoute $route
     * @throws \Exception
     */
    protected static function validateRouteIsNotEmpty(FullRoute $route): void
    {
        if (empty($route->getUrl())) {
            throw new \Exception("La ruta no puede estar vacía.");
        }
    }

    /**
     * Validar que el método de la ruta sea válido
     *
     * @param FullRoute $route
     * @throws \Exception
     */
    protected static function validateMethodIsValid(FullRoute $route): void
    {
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        if (!in_array($route->getUrlMethod(), $validMethods)) {
            throw new \Exception("El método '{$route->getUrlMethod()}' no es válido.");
        }
    }
}
