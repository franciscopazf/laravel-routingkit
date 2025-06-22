<?php

//namespace FPJ\RoutingKit\Support;

// Importa la clase de tu entidad principal (tu Query Builder)
use FPJ\RoutingKit\Entities\FPJNavigation;

if (!function_exists('fpj_navigation')) {
   
    /**
     * Obtiene una nueva instancia del Query Builder para FPJNavigation.
     *
     * @return \FPJ\RoutingKit\Entities\FPJNavigation
     */
    function fpj_navigation(): FPJNavigation
    {

        return FPJNavigation::newQuery();
    }
}
