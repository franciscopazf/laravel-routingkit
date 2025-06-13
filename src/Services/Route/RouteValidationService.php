<?php

namespace Fp\RoutingKit\Services\Route;

use Fp\RoutingKit\Clases\RoutingKit;
use Illuminate\Support\Collection;

class RouteValidationService
{
    protected Collection $routes;
    protected RoutingKit $route;

    /**
     * Constructor privado para obligar el uso de make()
     */
    private function __construct() {}

    /**
     * Método estático para crear la instancia
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Validar la ruta (completa)
     *
     * @throws \Exception
     */
    public function validateRoute(RoutingKit $route, Collection $routes): void
    {
        $this->route = $route;
        $this->routes = $routes;

        $this->validateIdIsNotEmpty();
        $this->validateIdIsUnique();
        $this->validateRouteIsNotEmpty();
        $this->validateMethodIsValid();
    }

    /**
     * Validar para insertar
     *
     * @throws \Exception
     */
    public function validateInsertRoute(RoutingKit $route): void
    {
        $this->route = $route;

        $this->validateIdIsNotEmpty();
        $this->validateRouteIsNotEmpty();
        $this->validateMethodIsValid();
    }

    /**
     * Validar para eliminar
     *
     * @throws \Exception
     */
    public function validateDeleteRoute(RoutingKit $route): void
    {
        $this->route = $route;
        $this->validateIdIsNotEmpty();
        $this->validateRouteIsNotEmpty();
        $this->validateMethodIsValid();
    }

    /**
     * Validar para mover
     *
     * @throws \Exception
     */
    public function validateMoveRoute(RoutingKit $route, Collection $routes): void
    {
        $this->route = $route;
        $this->routes = $routes;

        $this->validateIdIsNotEmpty();
        $this->validateRouteIsNotEmpty();
        $this->validateMethodIsValid();
    }

    /**
     * Validar que el ID no esté vacío
     */
    protected function validateIdIsNotEmpty(): void
    {
        if (empty($this->route->getId())) {
            throw new \Exception("El ID no puede estar vacío.");
        }
    }

    /**
     * Validar que el ID sea único
     */
    protected function validateIdIsUnique(): void
    {
        $flattened = $this->routes->flatten(1);
        $ids = $flattened->pluck('id');

        if ($ids->contains($this->route->getId())) 
            throw new \Exception("El ID '{$this->route->getId()}' ya existe.");
        
    }

    /**
     * Validar que la ruta no esté vacía
     */
    protected function validateRouteIsNotEmpty(): void
    {
        if (empty($this->route->getUrl())) {
            throw new \Exception("La ruta no puede estar vacía.");
        }
    }

    /**
     * Validar método HTTP
     */
    protected function validateMethodIsValid(): void
    {
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        if (!in_array($this->route->getUrlMethod(), $validMethods)) {
            throw new \Exception("El método '{$this->route->getUrlMethod()}' no es válido.");
        }
    }
}
