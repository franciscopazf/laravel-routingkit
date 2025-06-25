<?php

namespace FP\RoutingKit\Entities;

use FP\RoutingKit\Contracts\FPEntityInterface;
use FP\RoutingKit\Entities\FPRoute;
use FP\RoutingKit\Features\InteractiveFeature\FPParameterOrchestrator;
use FP\RoutingKit\Traits\HasDynamicAccessors;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class FPNavigation extends FPBaseEntity
{
    use HasDynamicAccessors;

    /**
     * @var string
     */
    public string $id;

    public string $makerMethod = 'make';

    public ?string $instanceRouteId = null;

    public ?string $parentId = null;

    public ?string $urlName = null;

    public ?string $url = null;

    public ?string $description = null;

    public ?string $accessPermission = null;

    public ?string $label = null;

    public ?int $bageInt = null;
    
    public ?int $acuntBageInt = null;

    public ?string $bageString = null;

    public ?string $heroIcon = null;

    public ?string $icon = null;

    public bool $isFpRoute = false;

    public bool $isGroup = false;

    public bool $isHidden = false;

    public bool $isActive = false;


    public array|Collection $items = [];

    public ?string $endBlock = null;

    public function __construct(string $id, ?string $instanceRouteId = null)
    {
        $this->id = $id;
        $this->label = $id; // Default label to id
        $this->instanceRouteId = $instanceRouteId ?? $id; // Default to id if instanceRouteId is not provided
        //$this->loadData();
    }

    public static function make(string $id, ?string $instanceRouteId = null): self
    {
        $instance = new self($id,  instanceRouteId: $instanceRouteId);
        $instance->loadFromFpRoute();
        $instance->makerMethod = 'make';
        return $instance;
    }

    public static function makeSelf(string $id, string $instanceRouteId): self
    {
        $instance = new self($id,  instanceRouteId: $instanceRouteId);
        $instance->makerMethod = 'makeSelf';
        return $instance;
    }

    public static function makeSimple(string $id, ?string $instanceRouteId = null): self
    {
        $instance = new self($id,  instanceRouteId: $instanceRouteId);
        $instance->makerMethod = 'makeSimple';
        $instance->loadFromSimpleRoute();
        return $instance;
    }

    public static function makeGroup(string $id): self
    {
        $instance = new self($id);
        $instance->makerMethod = 'makeGroup';
        $instance->isGroup = true; // Set as a group
        return $instance;
    }

    public static function makeLink(string $id): self
    {
        $instance = new self($id);
        $instance->makerMethod = 'makeLink';
        return $instance;
    }


    public function loadData(): self
    {
        if (FPRoute::exists($this->instanceRouteId)) {
            $this->loadFromFpRoute();
        } else if (Route::has($this->id)) {
            $this->loadFromSimpleRoute();
        } else {
            $this->urlName = $this->id; // Use the ID as the URL name
            $this->url = url($this->id); // Generate URL from ID
            $this->label = $this->id; // Use the ID as the label
            $this->accessPermission = null; // No access permission for simple routes
            $this->isFpRoute = false;
        }

        return $this;
    }


    public function loadFromSimpleRoute(): self
    {
        $this->urlName = $this->instanceRouteId ?? $this->id; // Use instanceRouteId if provided, otherwise use id
        $this->url = parse_url(url($this->urlName), PHP_URL_PATH);
        $this->label = $this->id;
        $this->accessPermission = null; // No access permission for simple routes

        return $this;
    }


    public function loadFromFpRoute(): self
    {

        $route = FPRoute::findById($this->instanceRouteId);

        if (!$route)
            throw new \InvalidArgumentException("Route not found for entity ID: {$this->instanceRouteId}");

        $this->urlName = $route->getFullUrl();
        $this->accessPermission = $route->getAccessPermission();
        $this->url = $route->getUrl();
        $this->label = $route->getId();

        return $this;
    }

    public function setIsGroup(bool $isGroup = true): self
    {
        $this->isGroup = $isGroup;
        return $this;
    }

    public function setinstanceRouteId(string $instanceRouteId): self
    {
        $this->instanceRouteId = $instanceRouteId;
        $this->loadFromFpRoute();
        return $this;
    }

    public function FPRoute(): ?FPEntityInterface
    {
        if (FPRoute::exists($this->instanceRouteId)) {
            return FPRoute::findById($this->instanceRouteId);
        }
        return null;
    }

    public function addItem(FPEntityInterface $item): static
    {
        if($item instanceof FPNavigation) {
            // si existe $bageInt entonces se suma al acuntBageInt
            if ($item->bageInt) {
                $this->acuntBageInt = ($this->acuntBageInt ?? 0) + $item->bageInt;
            }

        }
        
        parent::addItem($item);

        // 3. Devuelve $this para permitir el encadenamiento de métodos
        return $this;
    }


    public static function getOrchestratorConfig(): array
    {
        return config('routingkit.navigators_file_path');
    }

    public static function createConsoleAtributte(array $data): array
    {
        $attributes =  [
            'instanceRouteId' => [
                'type' => 'string_select',
                'rules' => ['string'],
                'closure' => fn() => FPRoute::seleccionar(null, 'Ruta a Crear Navegacion: '),
            ],

            'id' => [
                'type' => 'string',
                'rules' => [
                    'required',
                    'string',
                    'expect_false' => function($value) {
                        return FPNavigation::exists($value);
                    }
                ],
                'closure' => function($parametros){
                    if (!FPNavigation::exists($parametros['instanceRouteId'])) {
                       return $parametros['instanceRouteId'];
                    }
                }
            ],

            'parentId' => [
                'type' => 'string_select',
                'description' => 'Padre de la ruta seleccionado',
                'rules' => ['nullable', 'string'],
                'closure' => fn() => FPNavigation::seleccionar(null, 'Insertar en: ', true),
            ]
        ];

        return FPParameterOrchestrator::make()
            ->processParameters($data, $attributes);
    }

    public function getOmmittedAttributes(): array
    {
        return [
            'id' => ['omit'],
            'instanceRouteId' => ['omit'],
            'url' => ['same:FPRoute().url', 'isTrue:isGroup'],

            'makerMethod' => ['omit'],
            'urlName' => ['same:instanceRouteId'],
            'accessPermission' => ['same:FPRoute().accessPermission'],
            'label' => ['same:instanceRouteId'],

            'isFpRoute' => ['omit'],
            'isGroup' => ['omit'],
            'isHidden' => ['omit:false'],

            'acuntBageInt' => ['omit'],
            'isActive' => ['omit'],
            'items' => ['omit'],
            'endBlock' => ['omit'],
            'level' => ['omit'],
            'contextKey' => ['omit'],
        ];
    }
}
