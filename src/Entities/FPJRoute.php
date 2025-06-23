<?php

namespace FPJ\RoutingKit\Entities;

use FPJ\RoutingKit\Contracts\FPJEntityInterface;
use FPJ\RoutingKit\Features\InteractiveFeature\FPJileBrowser;
use FPJ\RoutingKit\Features\InteractiveFeature\FPJParameterOrchestrator;
use FPJ\RoutingKit\Routes\FPJRegisterRouter;
use FPJ\RoutingKit\Traits\HasDynamicAccessors;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule; // Importa Rule para usar Rule::in() si lo deseas

use FPJ\RoutingKit\Features\InteractiveNavigatorFeature\FPJInteractiveNavigator;

class FPJRoute extends FPJBaseEntity
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

    public ?string $prefix = ''; // Prefijo para la ruta, si es necesario

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

    public static function makeGroup(string $id): FPJEntityInterface
    {
        $instance = new static($id);
        $instance->id = $id;
        $instance->url = null; // No URL for groups
        $instance->urlName = null; // No URL name for groups
        $instance->urlMethod = null; // No method for groups
        $instance->urlController = null; // No controller for groups
        $instance->isGroup = true;

        $instance->makerMethod = 'makeGroup';
        return $instance;
    }

    public static function createConsoleAtributte(array $data): array
    {
         $attributes =  [
            'id' => [
                'type' => 'string',
                'description' => 'Identificador único de la ruta.',
                'rules' => ['required', 'string', 'expect_false' => fn($value) => FPJRoute::exists($value)],
            ],
            'parentId' => [
                'type' => 'string_select',
                'description' => 'Padre de la ruta seleccionado',
                'rules' => ['nullable', 'string', 'expect_false' => fn($value) => $value === null || !FPJRoute::exists($value)],
                'closure' => fn() => FPJRoute::seleccionar(null, 'Insertar en:', true),
            ],
            'accessPermission' => [
                'type' => 'string',
                'description' => 'Permiso de acceso a la ruta.',
                'rules' => ['nullable', 'string'],
            ],
            'urlController' => [
                'type' => 'string_select',
                'description' => 'Controlador asociado a la ruta.',
                'rules' => ['required', 'string'], 
                'closure' => fn() => FPJileBrowser::make()->browseFromPaths([
                    ['path' => base_path('app/Http/Controllers'), 'is_livewire' => false],
                    ['path' => base_path('app/Livewire'), 'is_livewire' => true],
                ]),
            ],
            'urlMethod' => [
                'type' => 'array_unique',
                'description' => 'Método HTTP asociado a la ruta, si es un grupo.',
                'rules' => ['required', 'string', 'min:1', 'in:get,post,put,delete,patch,options'], 
            ],
            'roles' => [
                'type' => 'array_multiple',
                'description' => 'A que roles permite el acceso a la ruta.',
                'rules' => ['nullable', 'array', 'in:'. implode(',', config('routingkit.roles', []))],
            ]
        ];
        return FPJParameterOrchestrator::make()
            ->processParameters($data, $attributes);
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
            'isActive' => ['omit'],
            'prefix' => ['is_blank'],
            'contextKey' => ['omit'],
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
        FPJRegisterRouter::registerRoutes();
    }

    public static function getOrchestratorConfig(): array
    {
        return config('routingkit.routes_file_path');
    }
}
