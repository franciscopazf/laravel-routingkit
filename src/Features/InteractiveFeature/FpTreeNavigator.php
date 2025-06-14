<?php

namespace Fp\RoutingKit\Features\InteractiveFeature;

use Fp\RoutingKit\Contracts\FpEntityInterface;
use Illuminate\Support\Collection;

use function Laravel\Prompts\select;

class FpTreeNavigator
{
    /**
     * Crea una nueva instancia de TreeNavigator.
     */
    public function __construct()
    {
        // Constructor vacío
    }
    /**
     * Crea una nueva instancia de TreeNavigator.
     *
     * @return self
     */
    public static function make(): self
    {
        return new self();
    }


    /**
     * Navega interactivamente por una colección de rutas RoutingKit.
     *
     * @param Collection|array $rutas
     * @param FpEntityInterface|null $nodoActual
     * @param array $pila
     * @param string|null $omitId
     * @return string|null
     */
    public function navegar(
        Collection|array $rutas,
        ?FpEntityInterface $nodoActual = null,
        ?array $pila = [],
        ?string $omitId = null
    ): ?string {

        $rutas = is_array($rutas) ? collect($rutas) : $rutas;
        $opciones = [];

        if ($nodoActual) {
            $hijos = is_array($nodoActual->getItems()) ?
                collect($nodoActual->getItems()) :
                $nodoActual->getItems();

            foreach ($hijos as $item) {
                if ($item->id === $omitId) continue;
                $opciones[$item->id] = '📁 ' . $item->id;
            }

            $opciones['__seleccionar__'] = '✅ Seleccionar esta ruta';

            if (!empty($pila)) {
                $opciones['__atras__'] = '🔙 Regresar';
            }
        } else {
            foreach ($rutas as $ruta) {
                if ($ruta->id === $omitId) continue;
                $opciones[$ruta->id] = '📁 ' . $ruta->id;
            }

            $opciones['__seleccionar__'] = '✅ Seleccionar una ruta raíz';
            $opciones['__salir__'] = '🚪 Salir';
        }

        $breadcrumb = collect($pila)
            ->pluck('id')
            ->push(optional($nodoActual)->id)
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
                ($nodoActual ? collect($nodoActual->getItems()) : $rutas)->firstWhere(fn($r) => $r->id === $seleccion),
                array_merge($pila, [$nodoActual]),
                $omitId
            ),
        };
    }
}
