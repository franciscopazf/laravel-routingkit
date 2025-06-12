<?php

//namespace Fp\FullRoute\Support;

// Importa la clase de tu entidad principal (tu Query Builder)
use Fp\FullRoute\Entities\FpNavigation;

if (!function_exists('fp_navigation')) {
   
    /**
     * Obtiene una nueva instancia del Query Builder para FpNavigation.
     *
     * @return \Fp\FullRoute\Entities\FpNavigation
     */
    function fp_navigation(): FpNavigation
    {

        return FpNavigation::newQuery();
    }
}
