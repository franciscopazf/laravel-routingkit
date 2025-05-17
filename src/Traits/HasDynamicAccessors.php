<?php

namespace Fp\FullRoute\Traits;

use Closure;
use ReflectionClass;

trait HasDynamicAccessors
{

    /**
     * Permite el acceso dinámico a los métodos de configuración de la ruta.
     * recibe el nombre del método y los argumentos como parámetros.
     * Si el método comienza con "set", se considera un setter.
     * Si el método comienza con "get", se considera un getter.
     * @param string $method
     * @param array $arguments
     * @return static|mixed
     * @throws \BadMethodCallException
     */
    public function __call(string $method, array $arguments)
    {
        if (str_starts_with($method, 'set')) {
            $property = lcfirst(substr($method, 3));
            return $this->setters($property, $arguments[0] ?? null);
        }

        if (str_starts_with($method, 'get')) {
            $property = lcfirst(substr($method, 3));
            return $this->getters($property);
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }

    protected function setters(string $property, mixed $value): static
    {
        if (!property_exists($this, $property)) {
            throw new \Exception("Property {$property} does not exist.");
        }

        if ($value instanceof \Closure) {
            // Paso el valor actual y el nombre de la propiedad
            $value = $value($this->{$property} ?? null, $property);
        }

        $this->{$property} = $value;
        return $this;
    }

    protected function getters(string $property): mixed
    {
        if (!property_exists($this, $property)) {
            throw new \Exception("Property {$property} does not exist.");
        }

        return $this->{$property};
    }

    public function getProperties(): array
    {
        $reflect = new \ReflectionClass($this);
        $props = $reflect->getProperties();
        $result = [];

        foreach ($props as $prop) {
            $prop->setAccessible(true);

            try {
                $value = $prop->getValue($this);
            } catch (\Throwable $e) {
                $value = null;
            }

            $result[$prop->getName()] = $value;
        }

        return $result;
    }
}
