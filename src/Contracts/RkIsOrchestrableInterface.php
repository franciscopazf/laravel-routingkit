<?php

namespace Rk\RoutingKit\Contracts;

interface RkIsOrchestrableInterface
{
    /**
     * Verifica si la entidad es orquestable.
     *
     * @return bool
     */
    public static function getOrchestratorConfig(): array;

}

   