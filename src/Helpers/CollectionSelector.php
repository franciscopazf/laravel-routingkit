<?php

namespace Fp\FullRoute\Helpers;

use Fp\FullRoute\Clases\FullRoute;
use Illuminate\Support\Collection;
use function Laravel\Prompts\select;

class CollectionSelector
{
    /**
     * Navega interactivamente por una colección de rutas FullRoute.
     *
     * @param Collection|array $rutas Colección o arreglo de FullRoute
     * @param FullRoute|null $nodoActual Nodo actual para mostrar sus hijos
     * @param array $pila Pila para retroceder en la navegación
     * @param string|null $omitId ID de la ruta que se debe omitir de la navegación
     * @return string Id de la ruta seleccionada
     */
    public static function navegar(
        Collection|array $rutas,
        ?FullRoute $nodoActual = null,
        array $pila = [],
        ?string $omitId = null
    ): string {
        $rutas = collect($rutas);
        $opciones = [];

        if ($nodoActual) {
            // Mostrar hijos del nodo actual
            foreach ($nodoActual->getChildrens() as $child) {
                if ($child->id === $omitId) continue;
                $opciones[$child->id] = '📁 ' . $child->title;
            }

            $opciones['__seleccionar__'] = '✅ Seleccionar esta ruta';

            if (!empty($pila)) {
                $opciones['__atras__'] = '🔙 Regresar';
            }
        } else {
            // Mostrar rutas raíz
            foreach ($rutas as $ruta) {
                if ($ruta->id === $omitId) continue;
                $opciones[$ruta->id] = '📁 ' . $ruta->title;
            }

            $opciones['__salir__'] = '🚪 Salir';
        }

        // Construir breadcrumb de la navegación
        $breadcrumb = collect($pila)
            ->filter()
            ->pluck('title')
            ->push(optional($nodoActual)->title)
            ->filter()
            ->implode(' > ');

        $seleccion = select(
            label: $breadcrumb ? "Ruta actual: {$breadcrumb}" : "Selecciona una ruta raíz",
            options: $opciones
        );

        // Control de la opción seleccionada
        return match ($seleccion) {
            '__salir__' => exit("🚪 Saliendo del navegador de rutas.\n"),
            '__seleccionar__' => $nodoActual->id,
            '__atras__' => self::navegar($rutas, array_pop($pila), $pila, $omitId),
            default => self::navegar(
                $rutas,
                ($nodoActual ? collect($nodoActual->getChildrens()) : $rutas)->firstWhere(fn($r) => $r->id === $seleccion),
                array_merge($pila, [$nodoActual]),
                $omitId
            ),
        };
    }


    
}
