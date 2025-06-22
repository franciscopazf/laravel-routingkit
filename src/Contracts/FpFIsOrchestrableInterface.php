<?php

namespace FpF\RoutingKit\Contracts;

interface FpFIsOrchestrableInterface
{
    /**
     * Verifica si la entidad es orquestable.
     *
     * @return bool
     */
    public static function getOrchestratorConfig(): array;

}

   