<?php

namespace Fp\FullRoute\Contracts;


use Fp\FullRoute\Clases\FullRoute;
use Illuminate\Support\Collection;


interface RouteServiceInterface
{
    // Create
    public function addRoute(FullRoute $route): void;
    
    // Read
    public static function getAllRoutes(): Collection;
    public static function findRoute(string $routeId): ?FullRoute;
    
    // Update
    public static function moveRoute(FullRoute $fromRoute, FullRoute $toRoute): void;
    
    // Delete
    public static function removeRoute(string $routeId): void;
}
