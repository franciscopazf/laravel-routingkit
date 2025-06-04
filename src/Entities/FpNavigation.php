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

    //public ?FpRoute $entity = null;

    public string $id;

    public ?string $parentId = null;

    public ?string $urlName = null;

    public ?string $url = null;

    public ?string $description = null;

    public ?string $accesPermission = null;

    public ?string $label = null;

    public ?string $heroIcon = null;

    public ?bool $isFpRoute = false;

    public array|Collection $childrens = [];

    public string $endBlock;

    private function __construct(string $id)
    {
        $this->id = $id;
        $this->entityId = $id; // Initialize entityId with the provided id
    }


    public static function make(string $id): self
    {
        $instance = new self($id);
        return $instance;
    }

    public function setIsFpRoute(bool $loadRoute = true): self
    {
        $route = FpRoute::findById($this->entityId);
        if ($route) {
            $this->urlName = $route->getId();
            $this->accesPermission= $route->getPermission();
            $this->url = $route->getUrl();
        } else {
            throw new \InvalidArgumentException("Route not found for ID: " . $this->id);
        }

        return $this;
    }


    public function setEntityId(String|FpRoute $entityId): self
    {
        if (is_string($entityId)) {
            $this->entityId = $entityId;
        } else if ($entityId instanceof FpRoute) {
            $this->entityId = $entityId->getId();
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
