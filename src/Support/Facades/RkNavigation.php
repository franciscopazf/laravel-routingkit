<?php

namespace Rk\RoutingKit\Support\Facades; // Asegúrate de que el namespace sea correcto para tu paquete

use Illuminate\Support\Facades\Facade; // Importa la clase base Facade de Laravel
      
class RkNavigation extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * Este método es crucial. Devuelve la clave que utilizaste para registrar
     * tu servicio en el Service Container (en el método register() del Service Provider).
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'rk.navigation'; // ¡Esta cadena debe coincidir con la clave del singleton!
    }
}