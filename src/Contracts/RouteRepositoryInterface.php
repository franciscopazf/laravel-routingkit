<?php

namespace Fp\FullRoute\Contracts;

use Illuminate\Support\Collection;
use Fp\FullRoute\Clases\FullRoute;

interface RouteRepositoryInterface
{
    public function getAllRoutes(): Collection;
    public function findRoute(string $routeId): ?FullRoute;
    public function addRoute(FullRoute $route): void;
    public function moveRoute(FullRoute $from, FullRoute $to): void;
    public function removeRoute(string $routeId): void;
}