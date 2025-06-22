<?php

//namespace FpF\RoutingKit\Support;

// Importa la clase de tu entidad principal (tu Query Builder)
use FpF\RoutingKit\Entities\FpFNavigation;

if (!function_exists('fpf_navigation')) {
   
    /**
     * Obtiene una nueva instancia del Query Builder para FpFNavigation.
     *
     * @return \FpF\RoutingKit\Entities\FpFNavigation
     */
    function fpf_navigation(): FpFNavigation
    {

        return FpFNavigation::newQuery();
    }
}
