<?php

namespace Fp\FullRoute\Services;
/*
     * ESTA CLASE, RECIVE COMO PARAMETRO UNA COLLECTION DE FULLROUTE
     * Y "FORMATEA LA COLLECCION IDENTANDOLA CORRECTAMENTE
     * ADEMAS DE "PARSEARLA" A UN ARRAY PLANO O A FORMATO DE ARBOL
     *
     */

use Fp\FullRoute\Clases\FullRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Fp\FullRoute\Services\RouteContentManager;

class Transformer
{

    public function __construct(
        private RouteContentManager $routeContentManager,
        private Collection $fullRouteCollection,
    ) {}


    private function prepareContent(): void
    {

        // esta funcion recive como parametro una collection de full route las 
        // recorre y para cada una de las rutas las mapea y le agrega el respectivo
        // bloque leyendo el contenido de la ruta ()
        // obtiene el bloque de la ruta en string ()
        // y lo agrega nuevamente a la ruta esa sera la representacion de la ruta
        // en texto 
        // se podran hacer cambios en el bloque mediante collecciones pero lo que se guardara
        // sera el bloque en texto
        // esta clase solo se encarga de tranformar el contenido pero no de guardarlo
        // para eso se delegara a la clase RouteContentManager

    }
}
