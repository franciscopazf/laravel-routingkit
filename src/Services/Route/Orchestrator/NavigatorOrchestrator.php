<?php

namespace Fp\FullRoute\Services\Route\Orchestrator;

use Fp\FullRoute\Contracts\OrchestratorInterface;
use Fp\FullRoute\Services\Route\Orchestrator\BaseOrchestrator;
use Fp\FullRoute\Services\Route\Strategies\RouteStrategyFactory;
use Fp\FullRoute\Services\Route\RouteContext;


class NavigatorOrchestrator extends BaseOrchestrator implements OrchestratorInterface
{
    
    protected static ?self $instance = null;

    public $finalFlattenned = [];

    public static function make(): self
    {
        if (self::$instance === null)
            self::$instance = new self();
        return self::$instance;
    }

    public function prepareContext(array $contextData): mixed
    {

        $context = RouteStrategyFactory::make(
            $contextData['support_file'],
            $contextData['path'],
            $contextData['only_string_support'] ?? true
        );

        return $context;
    }

    public function getDefaultContext(): ?RouteContext
    {
        // Retorna el primer contexto si existe, o null si no hay contextos
        $position = config('fproute.navigators_file_path.defaul_file_path_position', 0);
        return $this->contexts[$position] ?? null;
    }

    public function loadFromConfig(): void
    {

        # dd("Cargando rutas desde la configuración...");
        $configs = config('fproute.navigators_file_path.items');
        //dd($configs);
        //dd($this->contexts);

        #dd($configs);
        foreach ($configs as $config) {
            $context = $this->prepareContext($config);
            $this->contexts[] = $context;

            $flatRoutes = $context->getAllFlattenedRoutes();
            foreach ($flatRoutes as $route)
                $this->entityMap[$route->getId()] = $context;
        }
        #dd($this->contexts);
        #dd("Rutas cargadas desde la configuración.");
    }

    public function getAllOnlyRolHasPermissions($role): array
    {

    }

    public function getAllInPermissionsList(array $permissions): array
    {

    }

}
