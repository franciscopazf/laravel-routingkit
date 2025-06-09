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

    public ?string $accessPermission = null;

    public ?string $label = null;

    public ?string $heroIcon = null;

    public ?bool $isFpRoute = false;

    public ?bool $isGroup = false;

    public ?bool $isHidden = false;

    public bool $isActive = false;

    public array|Collection $childrens = [];

    public string $endBlock;



    private function __construct(string $id, bool $isGroup = false)
    {
        $this->id = $id;
        $this->isGroup = $isGroup; // Initialize isGroup with the provided value
        $this->entityId = $id; // Initialize entityId with the provided id
    }


    public static function make(string $id): self
    {
        $instance = new self($id, $isGroup = false);
        return $instance;
    }

    public function getOmmittedAttributes(): array
    {
        return [
            'id',
            'urlName',
            'childrens',
            'endBlock',
            'level',
        ];
    }

    public function setIsGroup(bool $isGroup = true): self
    {
        $this->isGroup = $isGroup;
        return $this;
    }

    public function setIsFpRoute(bool $loadRoute = true): self
    {
        $route = FpRoute::findById($this->entityId);
        if ($route) {
            $this->urlName = $route->getId();
            $this->accessPermission = $route->getAccessPermission();
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
