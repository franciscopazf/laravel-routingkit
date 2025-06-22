<?php

namespace FPJ\RoutingKit\Contracts;

interface FPJIsOrchestrableInterface
{
    /**
     * Verifica si la entidad es orquestable.
     *
     * @return bool
     */
    public static function getOrchestratorConfig(): array;

}

   