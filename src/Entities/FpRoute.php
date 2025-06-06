<?php

namespace Fp\FullRoute\Entities;

use Fp\FullRoute\Contracts\OrchestratorInterface;
use Fp\FullRoute\Helpers\RegisterRouter;
use Fp\FullRoute\Services\Navigator\Navigator;
use Fp\FullRoute\Services\Route\Orchestrator\RouteOrchestrator;
use Fp\FullRoute\Traits\HasDynamicAccessors;
use Illuminate\Support\Collection;

class FpRoute extends FpBaseEntity
{
    use HasDynamicAccessors;

    public ?string $parentId = null;

    public $url;
    public $urlName;
    public $urlMethod;
    public $urlController;
    public $urlAction;

    public $urlMiddleware = [];
    public ?string $accessPermission = null;
    public $permissions = [];
    public $roles = [];
    public array|Collection $childrens = [];
    public $endBlock;

    public static function make(string $id): static
    {
        $instance = new static($id);
        $instance->id = $id;
        $instance->url = '/' . ltrim($id, '/');
        $instance->urlName = str_replace('/', '.', $id);

        return $instance;
    }

    public function setPermission($permission): self
    {
        // Si es un Closure, lo ejecutamos para obtener el string
        if ($permission instanceof \Closure) {
            $permission = $permission();
        }
        // Ahora $permission es un string
        $this->accessPermission = $permission;
        // $this->permissions[] = $permission;
        //$this->urlMiddleware[] = 'permission:' . $permission;

        return $this;
    }

    public function getAllPermissions(): array
    {
       // dd('getAllPermissions', $this->accessPermission, $this->permissions);
        return array_filter(
            array_merge([$this->accessPermission], $this->permissions),
            fn($permission) => trim($permission) !== ''
        );
    }


    public static function getOrchestrator(): OrchestratorInterface
    {
        return RouteOrchestrator::make();
    }

    public static function registerRoutes(): void
    {
        RegisterRouter::registerRoutes();
    }

    public static function seleccionar(?string $omitId = null, string $label = 'Selecciona una ruta'): ?string
    {
        return Navigator::make()
            ->treeNavigator(FpRoute::all());
    }
}
