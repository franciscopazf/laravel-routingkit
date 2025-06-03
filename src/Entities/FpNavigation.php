<?php

namespace Fp\FullRoute\Entities;


use Fp\FullRoute\Contracts\FpEntityInterface;
use Fp\FullRoute\Entities\FpRoute;
use Fp\FullRoute\Traits\HasDynamicAccessors;
use Fp\FullRoute\Contracts\OrchestratorInterface;
use Fp\FullRoute\Services\Route\Orchestrator\NavigatorOrchestrator;
use Fp\FullRoute\Services\Route\Orchestrator\RouteOrchestrator;

use Illuminate\Support\Collection;

class FpNavigation extends FpBaseEntity
{
    use HasDynamicAccessors;
    /**
     * @var string
     */
    protected string $entityId;

    public ?FpRoute $entity = null;

    public string $id;

    public ?string $parentId = null;

    public array|Collection $childrens = [];

    public string $endBlock;

    public function __construct() {}

    public static function make(string $id): self
    {
        $instance = new self();
        $instance->id = $id;
        if (FpRoute::exists($id)) {
            $instance->entity = FpRoute::findById($id);
            $instance->entityId = $instance->entity->getId();
        }

        return $instance;
    }

    public function setEntityId(String|FpRoute $entityId): self
    {
        if (is_string($entityId)) {
            $this->entityId = $entityId;
            $this->entity = FpRoute::findById($entityId);
        } else if ($entityId instanceof FpRoute) {
            $this->entityId = $entityId->getId();
            $this->entity = $entityId;
        } else {
            throw new \InvalidArgumentException("Entity ID must be a string or an instance of FpRoute.");
        }
        return $this;
    }


    public static function getOrchestrator(): OrchestratorInterface
    {
        return NavigatorOrchestrator::make();
    }

    public static function seleccionar(?string $omitId = null, string $label = 'Selecciona una ruta'): ?string
    {
        return ""; // Implementación pendiente

    }
}
