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

    public $permission;
    public $title;
    public $description;
    public $keywords;
    public $icon;
    public $url;
    public $urlName;
    public $urlMethod;
    public $urlController;
    public $urlAction;
    public $fullUrlName;
    public $fullUrl;
    public $permissions = [];
    public $roles = [];
    public array|Collection $childrens = [];
    public $endBlock;

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
