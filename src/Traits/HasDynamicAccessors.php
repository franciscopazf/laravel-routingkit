<?php

namespace Rk\RoutingKit\Traits;

use Closure;
use ReflectionClass;
use ReflectionProperty; // Asegúrate de importar esto

trait HasDynamicAccessors
{
    /**
     * @var array Un caché de propiedades dinámicas para evitar re-chequeos de existencia.
     */
    protected array $dynamicProperties = [];

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

    /**
     * Maneja la asignación de valores a propiedades.
     * Crea la propiedad dinámicamente si no existe.
     *
     * @param string $property El nombre de la propiedad.
     * @param mixed $value El valor a asignar.
     * @return static
     */
    protected function setters(string $property, mixed $value): static
    {
        // Comprobar si la propiedad ya existe o si ya la hemos registrado como dinámica.
        // reflectionProperty::isInitialized($this, $property) no funciona para propiedades dinámicas.
        // Usamos property_exists para propiedades declaradas y luego nuestro propio cache para las dinámicas.
        if (property_exists($this, $property) || array_key_exists($property, $this->dynamicProperties)) {
             // Si la propiedad es declarada o ya dinámica
            if ($value instanceof \Closure) {
                $this->{$property} = $value($this->{$property} ?? null, $property);
            } else {
                $this->{$property} = $value;
            }
        } else {
            // La propiedad no existe, la creamos dinámicamente
            $this->{$property} = $value instanceof \Closure ? $value(null, $property) : $value;
            $this->dynamicProperties[$property] = true; // Marcamos como propiedad dinámica
        }

        return $this;
    }

    /**
     * Maneja la obtención de valores de propiedades.
     *
     * @param string $property El nombre de la propiedad.
     * @return mixed El valor de la propiedad.
     * @throws \Exception Si la propiedad no existe (declarada o dinámica).
     */
    protected function getters(string $property): mixed
    {
        // Comprobar si la propiedad existe como declarada o dinámica
        if (property_exists($this, $property) || array_key_exists($property, $this->dynamicProperties)) {
            return $this->{$property};
        }

        throw new \Exception("Property {$property} does not exist.");
    }

    /**
     * Obtiene todas las propiedades (declaradas y dinámicas) del objeto.
     * @return array
     */
    public function getProperties(): array
    {
        $reflect = new ReflectionClass($this);
        $result = [];

        // Obtener propiedades declaradas
        foreach ($reflect->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE) as $prop) {
            $prop->setAccessible(true); // Asegurar accesibilidad
            $result[$prop->getName()] = $prop->getValue($this);
        }

        // Añadir propiedades dinámicas que no estén ya en las declaradas
        foreach ($this->dynamicProperties as $dynamicPropName => $dummy) {
            if (!isset($result[$dynamicPropName])) {
                $result[$dynamicPropName] = $this->{$dynamicPropName};
            }
        }

        return $result;
    }
}