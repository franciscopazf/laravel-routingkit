<?php

namespace Fp\FullRoute\Entities;


use Fp\FullRoute\Contracts\FpEntityInterface;
use Fp\FullRoute\Entities\FpRoute;
use Fp\FullRoute\Traits\HasDynamicAccessors;
use Fp\FullRoute\Contracts\OrchestratorInterface;
use Fp\FullRoute\Services\Route\Orchestrator\NavigatorOrchestrator;
use Fp\FullRoute\Services\Route\Orchestrator\RouteOrchestrator;
use Fp\FullRoute\Services\Navigator\Navigator;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\Route; // ¡Importante!


class FpNavigation extends FpBaseEntity
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

    public ?string $heroIcon = null;

    public bool $isFpRoute = false;

    public bool $isGroup = false;

    public bool $isHidden = false;

    public bool $isActive = false;


    public array|Collection $childrens = [];

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
        if (FpRoute::exists($this->instanceRouteId)) {
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

        $route = FpRoute::findById($this->instanceRouteId);

        if (!$route)
            throw new \InvalidArgumentException("Route not found for entity ID: {$this->instanceRouteId}");

        $this->urlName = $route->getId();
        $this->accessPermission = $route->getAccessPermission();
        $this->url = $route->getUrl();
        $this->label = $route->getId();

        return $this;
    }

    public function getOmmittedAttributes(): array
    {
        return [
            'id' => ['omit'],
            'instanceRouteId' => ['omit'], //['same:id', 'isTrue:isFpRoute'],
            'url' => ['same:FpRoute().url', 'isTrue:isGroup'],

            'makerMethod' => ['omit'],
            'urlName' => ['same:instanceRouteId'],
            'accessPermission' => ['same:FpRoute().accessPermission'],
            'label' => ['same:instanceRouteId'],

            'isFpRoute' => ['omit'],
            'isGroup' => ['omit'],
            'isHidden' => ['omit:false'],

            'isActive' => ['omit'],
            'childrens' => ['omit'],
            'endBlock' => ['omit'],
            'level' => ['omit'],
        ];
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

    public function FpRoute(): ?FpEntityInterface
    {
        if (FpRoute::exists($this->instanceRouteId)) {
            return FpRoute::findById($this->instanceRouteId);
        }
        return null;
    }

    public static function getOrchestrator(): OrchestratorInterface
    {
        return NavigatorOrchestrator::make();
    }

    public static function seleccionar(?string $omitId = null, string $label = 'Selecciona una ruta'): ?string
    {
        return Navigator::make()
            ->treeNavigator(FpNavigation::all());
    }
}
