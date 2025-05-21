<?php

namespace Fp\FullRoute\Contracts;

use Illuminate\Support\Collection;

interface RouteEntityInterface
{
    // Métodos estáticos
    public static function Make(string $id): self;
    public static function find(string $id): ?self;
    public static function all(): Collection;
    public static function allFlattened(): Collection;
    public static function exists(string $id): bool;
    public static function seleccionar(?string $omitId = null, string $label = 'Selecciona una ruta'): string;
    public static function RegisterRoutes(): void;

    // Métodos de instancia
    public function save(string|self $parent): self;
    public function delete(): self;
    public function moveTo(string|self $parent): self;
    public function getParentRoute(): ?self;
    public function routeIsChild(string $id): bool;
}
