<?php

//namespace FP\RoutingKit\Support;

// Importa la clase de tu entidad principal (tu Query Builder)
use FP\RoutingKit\Entities\FPNavigation;

if (!function_exists('fp_navigation')) {
   
    /**
     * Obtiene una nueva instancia del Query Builder para FPNavigation.
     *
     * @return \FP\RoutingKit\Entities\FPNavigation
     */
    function fp_navigation(): FPNavigation
    {

        return FPNavigation::getInstance();
    }
}
