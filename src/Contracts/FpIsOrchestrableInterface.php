<?php

namespace Fp\RoutingKit\Contracts;

interface FpIsOrchestrableInterface
{
    /**
     * Verifica si la entidad es orquestable.
     *
     * @return bool
     */
    public static function getOrchestratorConfig(): array;

}

   