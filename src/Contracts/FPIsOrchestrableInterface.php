<?php

namespace FP\RoutingKit\Contracts;

interface FPIsOrchestrableInterface
{
    /**
     * Verifica si la entidad es orquestable.
     *
     * @return bool
     */
    public static function getOrchestratorConfig(): array;

}

   