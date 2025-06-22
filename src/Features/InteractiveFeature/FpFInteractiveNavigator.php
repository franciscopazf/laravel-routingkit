<?php

namespace FpF\RoutingKit\Features\InteractiveFeature;

use FpF\RoutingKit\Contracts\FpFEntityInterface;
use FpF\RoutingKit\Contracts\FpFInteractiveInterface;

use function Laravel\Prompts\confirm;

class FpFInteractiveNavigator implements FpFInteractiveInterface
{
    protected string $entityClass;

    public function __construct(string $entityClass)
    {
        if (!class_exists($entityClass)) {
            throw new \InvalidArgumentException("La clase {$entityClass} no existe.");
        }

        if (!is_subclass_of($entityClass, FpFEntityInterface::class)) {
            throw new \InvalidArgumentException("La clase {$entityClass} debe implementar la interface FpFEntityInterface.");
        }

        $this->entityClass = $entityClass;
    }

    public static function make(string $entityClass): self
    {
        return new self($entityClass);
    }

    public function crear(array $data = []): FpFEntityInterface
    {
        $data = $this->entityClass::createConsoleAtributte($data);
        $entity = $this->entityClass::buildFromArray($data);
        $entity->save($entity->getParentId());
        $this->info("✅'{$entity->id}' creada correctamente.");
        return $entity;
    }

    public function eliminar(?string $id = null)
    {
        $entityClass = $this->entityClass;

        $id = $id ?? $entityClass::seleccionar(label: '🗑️ Eliminar :', permitirSeleccionarRaiz: false);
        $entidad = $entityClass::findById($id);

        if (!$entidad) {
            return $this->error("❌ No se encontró la entidad con ID '{$id}'.");
        }

        $this->confirmar("⚠️ ¿Estás seguro de que deseas eliminar el elemento con ID '{$id}'? Esta acción no se puede deshacer.");
        $entidad->delete();
    }

    public function reescribir()
    {
        $entityClass = $this->entityClass;

        $this->confirmar("🔄 ¿Estás seguro de que deseas reescribir todos los archivos? Esto actualizará todas las entidads existentes.");
        $entityClass::rewriteAllContext();
        $this->info("✅ entidades reescritas correctamente.");
    }

    protected function confirmar(
        string $mensaje,
        string $messageYes = 'Opción Aceptada',
        string $messageNo = 'Opción Cancelada',
    ): mixed {
        $confirmacion = confirm($mensaje, default: false);
        if (!$confirmacion) {
            $this->error($messageNo);
            die();
        }
        $this->info($messageYes);
        return $confirmacion;
    }

    protected function info(string $mensaje): void
    {
        echo "\e[32m{$mensaje}\e[0m\n"; // Verde
    }

    protected function error(string $mensaje): void
    {
        echo "\e[31m{$mensaje}\e[0m\n"; // Rojo
    }
}
