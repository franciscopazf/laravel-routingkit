<?php

//namespace Rk\RoutingKit\Support;

// Importa la clase de tu entidad principal (tu Query Builder)
use Rk\RoutingKit\Entities\RkNavigation;

if (!function_exists('rk_navigation')) {
   
    /**
     * Obtiene una nueva instancia del Query Builder para RkNavigation.
     *
     * @return \Rk\RoutingKit\Entities\RkNavigation
     */
    function rk_navigation(): RkNavigation
    {

        return RkNavigation::getInstance();
    }
}
