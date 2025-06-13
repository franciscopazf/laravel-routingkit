<?php

//namespace Fp\RoutingKit\Support;

// Importa la clase de tu entidad principal (tu Query Builder)
use Fp\RoutingKit\Entities\FpNavigation;

if (!function_exists('fp_navigation')) {
   
    /**
     * Obtiene una nueva instancia del Query Builder para FpNavigation.
     *
     * @return \Fp\RoutingKit\Entities\FpNavigation
     */
    function fp_navigation(): FpNavigation
    {

        return FpNavigation::newQuery();
    }
}
