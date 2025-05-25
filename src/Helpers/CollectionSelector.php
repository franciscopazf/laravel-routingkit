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
    ): ?string {

        // Asegurarse de que $rutas es una colección
        $rutas = is_array($rutas) ? collect($rutas) : $rutas;
        $opciones = [];
        if ($nodoActual) {
            // Obtener hijos como colección (compatibilidad array o colección)
            $hijos = $nodoActual->getChildrens();
            $hijos = is_array($hijos) ? collect($hijos) : $hijos;

            foreach ($hijos as $child) {
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

            $opciones['__seleccionar__'] = '✅ Seleccionar una ruta raíz';
            $opciones['__salir__'] = '🚪 Salir';
        }

        // Construir breadcrumb
        $breadcrumb = collect($pila)
            ->pluck('title')
            ->push(optional($nodoActual)->title)
            ->filter()
            ->implode(' > ');

        $seleccion = select(
            label: $breadcrumb ? "Ruta actual: {$breadcrumb}" : "Selecciona una ruta raíz",
            options: $opciones
        );

        return match ($seleccion) {
            '__salir__' => exit("🚪 Saliendo del navegador de rutas.\n"),
            '__seleccionar__' => $nodoActual?->id ?? null,
            '__atras__' => self::navegar($rutas, array_pop($pila), $pila, $omitId),
            default => self::navegar(
                $rutas,
                // Buscar siguiente nodo en hijos o rutas raíz
                ($nodoActual
                    ? collect($nodoActual->getChildrens())
                    : $rutas
                )->firstWhere(fn($r) => $r->id === $seleccion),
                array_merge($pila, [$nodoActual]),
                $omitId
            ),
        };
    }
}
