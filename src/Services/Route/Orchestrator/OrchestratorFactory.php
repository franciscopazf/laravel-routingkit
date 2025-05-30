<?php 

namespace Fp\FullRoute\Services\Route\Orchestrator;

class OrchestratorFactory
{
    /**
     * Create a new RouteOrchestrator instance.
     *
     * @return RouteOrchestrator
     */
    public static function make(): RouteOrchestrator
    {
        return new RouteOrchestrator();
    }
}