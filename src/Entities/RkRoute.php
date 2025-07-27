<?php

namespace Rk\RoutingKit\Entities;

use Rk\RoutingKit\Contracts\RkEntityInterface;
use Rk\RoutingKit\Features\InteractiveFeature\RkileBrowser;
use Rk\RoutingKit\Features\InteractiveFeature\RkParameterOrchestrator;
use Rk\RoutingKit\Routes\RkRegisterRouter;
use Rk\RoutingKit\Traits\HasDynamicAccessors;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

use Rk\RoutingKit\Features\InteractiveNavigatorFeature\RkInteractiveNavigator;

class RkRoute extends RkBaseEntity
{
    use HasDynamicAccessors;

    public ?string $parentId = null;
    public ?string $accessPermission = null;

    public ?string $url = null;
    public ?string $urlName = null;
    public ?string $fullUrl = null;
    public bool $isGroup = false;
    public bool $isActive = false;

    public $urlMethod;
    public $urlController;

    public ?string $prefix = null;
    public ?string $herePrefix = null;

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

    public static function makeGroup(string $id): RkEntityInterface
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
                'rules' => ['required', 'string', 'expect_false' => fn($value) => RkRoute::exists($value)],
            ],
            'parentId' => [
                'type' => 'string_select',
                'description' => 'Padre de la ruta seleccionado',
                'rules' => ['nullable', 'string', 'expect_false' => fn($value) => $value === null || !RkRoute::exists($value)],
                'closure' => fn() => RkRoute::seleccionar(null, 'Insertar en:', true),
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
                'closure' => fn() => RkileBrowser::make()->browseFromPaths([
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
                'rules' => ['nullable', 'array', 'in:' . implode(',', config('routingkit.roles', []))],
            ]
        ];
        return RkParameterOrchestrator::make()
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
            'fullUrl' => ['omit'],

            'acuntBageInt' => ['omit'],

            'prefix' => ['isblank'],
            'herePrefix' => ['omit'],
            'isGroup' => ['omit'],
            'isActive' => ['omit'],
            'contextKey' => ['omit'],
            'childrens' => ['omit'],
            'endBlock' => ['omit'],
        ];
    }

    public function setPermission($permission): self
    {
        if ($permission instanceof \Closure) {
            $permission = $permission();
        }
        $this->accessPermission = $permission;

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

    public function setPrefix(string $prefix): self
    {
        $this->prefix = '/' . trim($prefix, '/');
        $this->herePrefix = $this->prefix; // Aquí se establece el prefijo aquí
        return $this;
    }


    public function setUrl(?string $url): static
    {
        $this->url = $url ? '/' . trim($url, '/') : null;
        return $this;
    }
    
    public function addItem(RkEntityInterface $item): static
    {
        // 1. Asegura que la lógica base del padre se ejecute primero
        parent::addItem($item);

        // 2. Si el ítem es una ruta, aplicar lógica de prefijos
        if ($item instanceof RkRoute) {
            // Limpiar los prefijos



            // padre // item -> herePrefix
            // herePrefix // item 
            $item->herePrefix = $this->herePrefix . $item->prefix;
            $url = trim($item->url ?? '', '/');
            $item->fullUrl = rtrim($item->herePrefix ?? '', '/') . ($url !== '' ? '/' . $url : '');
        }

        // 3. Encadenamiento fluido
        return $this;
    }

    public function getFullUrl(): string
    {
        // Si la ruta es un grupo, devuelve el prefijo completo
        if ($this->isGroup) {
            return $this->herePrefix;
        }

        // Si no es un grupo, devuelve la URL completa
        return $this->fullUrl ?? $this->url;
    }


    public static function registerRoutes(): void
    {
        RkRegisterRouter::registerRoutes();
    }

    public static function getOrchestratorConfig(): array
    {
        return config('routingkit.routes_file_path');
    }
}
