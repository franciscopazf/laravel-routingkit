<?php

namespace FP\RoutingKit\Entities;

use FP\RoutingKit\Contracts\FPEntityInterface;
use FP\RoutingKit\Features\InteractiveFeature\FPileBrowser;
use FP\RoutingKit\Features\InteractiveFeature\FPParameterOrchestrator;
use FP\RoutingKit\Routes\FPRegisterRouter;
use FP\RoutingKit\Traits\HasDynamicAccessors;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

use FP\RoutingKit\Features\InteractiveNavigatorFeature\FPInteractiveNavigator;

class FPRoute extends FPBaseEntity
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

    public ?string $prefix = "";
    public ?string $fullPrefix = null;

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

    public static function makeGroup(string $id): FPEntityInterface
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
                'rules' => ['required', 'string', 'expect_false' => fn($value) => FPRoute::exists($value)],
            ],
            'parentId' => [
                'type' => 'string_select',
                'description' => 'Padre de la ruta seleccionado',
                'rules' => ['nullable', 'string', 'expect_false' => fn($value) => $value === null || !FPRoute::exists($value)],
                'closure' => fn() => FPRoute::seleccionar(null, 'Insertar en:', true),
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
                'closure' => fn() => FPileBrowser::make()->browseFromPaths([
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
        return FPParameterOrchestrator::make()
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
            'fullPrefix' => ['omit'],
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

    public function addItem(FPEntityInterface $item): static
    {
        // 1. Llama al método original de la clase padre
        // Esto asegura que la lógica base (establecer parentId y añadir a $items) se ejecute.


        // 2. Agrega tu lógica adicional específica para FPRoute, manejando los prefijos
        if ($item instanceof FPRoute) {
            $prefix = trim($this->prefix, '/');
            $fullPrefix = trim($this->fullPrefix, '/');

            // Construimos el nuevo fullPrefix solo si hay algo que agregar
            $segments = [];

            if ($fullPrefix !== '') {
                $segments[] = $fullPrefix;
            }

            if ($prefix !== '') {
                $segments[] = $prefix;
            }

            $this->fullPrefix = '/' . implode('/', $segments);

            // Propagar solo si hay un fullPrefix válido
            $item->fullPrefix = $this->fullPrefix;

            // Evitar doble slash en la URL final
            $url = trim($item->url, '/');
            $item->fullUrl = rtrim($item->fullPrefix, '/') . ($url !== '' ? '/' . $url : '');
        }

        parent::addItem($item);

        // 3. Devuelve $this para permitir el encadenamiento de métodos
        return $this;
    }

    public function getFullUrl(): string
    {
        // Si la ruta es un grupo, devuelve el prefijo completo
        if ($this->isGroup) {
            return $this->fullPrefix;
        }

        // Si no es un grupo, devuelve la URL completa
        return $this->fullUrl ?? $this->url;
    }


    public static function registerRoutes(): void
    {
        FPRegisterRouter::registerRoutes();
    }

    public static function getOrchestratorConfig(): array
    {
        return config('routingkit.routes_file_path');
    }
}
