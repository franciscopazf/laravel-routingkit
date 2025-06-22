<?php

namespace FpF\RoutingKit\Features\InteractiveFeature;

use ReflectionClass;
use ReflectionMethod;

class FpFClassInspector
{

    public function __construct()
    {
        // Constructor can be used for dependency injection if needed
    }

    public static function make(): self
    {
        return new self();
    }

    public function getPublicMethods(string $fullClass): array
    {

        if (!class_exists($fullClass)) {
            throw new \RuntimeException("La clase {$fullClass} no existe.");
        }

        $ref = new ReflectionClass($fullClass);

        return collect($ref->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn($method) => $method->class === $ref->getName() && !$this->isMagic($method->name))
            ->map(fn($method) => $method->name)
            ->values()
            ->toArray();
    }

    private function isMagic(string $method): bool
    {

        return str_starts_with($method, '__');
    }
}
