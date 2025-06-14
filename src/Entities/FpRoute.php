<?php

namespace Fp\RoutingKit\Entities;

use Fp\RoutingKit\Contracts\FpEntityInterface;
use Fp\RoutingKit\Routes\FpRegisterRouter;
use Fp\RoutingKit\Traits\HasDynamicAccessors;
use Illuminate\Support\Collection;
use Fp\RoutingKit\Features\InteractiveNavigatorFeature\FpInteractiveNavigator;

class FpRoute extends FpBaseEntity 
{
    use HasDynamicAccessors;

    public ?string $parentId = null;

    public ?string $accessPermission = null;

    // Propiedades adicionales (ej. para navegación, puedes expandir con HasDynamicAccessors)
    public ?string $url = null;
    public ?string $urlName = null;
    public bool $isGroup = false; // Indica si la entidad representa un grupo de rutas
    public bool $isActive = false; // Indica si la entidad está activa en la ruta actual


    public $urlMethod; // omit if is GET
    public $urlController;
    public $urlAction;


    public $urlMiddleware = [];
    public $permissions = [];
    public $roles = [];
    public array|Collection $items = [];
    public $endBlock;

    public static function make(string $id): static
    {
        $instance = new static($id);
        $instance->id = $id;
        $instance->url = '/' . ltrim($id, '/');
        $instance->urlName = str_replace('/', '.', $id);

        return $instance;
    }

    public static function makeGroup(string $id): FpEntityInterface
    {
        $instance = new static($id);
        $instance->id = $id;
        $instance->url = null; // No URL for groups
        $instance->urlName = null; // No URL name for groups
        $instance->urlMethod = null; // No method for groups
        $instance->urlController = null; // No controller for groups
        $instance->urlAction = null; // No action for groups
        $instance->isGroup = true;
        
        $instance->makerMethod = 'makeGroup';
        return $instance;
    }

    public function getOmmittedAttributes(): array
    {
        return [
            'id' => ['omit'],

            'url' => ['same:id'],
            'urlName' => ['same:id'],

            'makerMethod' => ['omit'],
            'level' => ['omit'],

            'roles' => ['minElements:1'],
            'urlMiddleware' => ['minElements:1'],
            'permissions' => ['minElements:1'],

            'isGroup' => ['omit'],


            'childrens' => ['omit'],
            'endBlock' => ['omit'],
        ];
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


  
    public static function registerRoutes(): void
    {
        FpRegisterRouter::registerRoutes();
    }

    public static function getOrchestratorConfig(): array
    {
        return config('routingkit.routes_file_path');
    }

    public static function seleccionar(?string $omitId = null, string $label = 'Selecciona una ruta'): ?string
    {
        return FpInteractiveNavigator::make()
            ->treeNavigator(FpRoute::all());
    }
}
