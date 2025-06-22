<?php

namespace FpF\RoutingKit\Features\InteractiveFeature;

use FpF\RoutingKit\Contracts\FpFEntityInterface;
use Illuminate\Support\Collection;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class FpFTreeNavigator
{
    public function __construct(public bool $soloGrupos = false) {}

    public static function make(bool $soloGrupos = false): self
    {
        return new self($soloGrupos);
    }

    public function navegar(
        Collection|array $rutas,
        ?FpFEntityInterface $nodoActual = null,
        ?array $pila = [],
        ?string $omitId = null
    ): ?string {
        $rutas = is_array($rutas) ? collect($rutas) : $rutas;
        $opciones = [];

        if ($nodoActual) {
            $hijos = collect($nodoActual->getItems());

            if ($this->soloGrupos) {
                $hijos = $hijos->filter(fn($item) => $item->isGroup);
            }

            foreach ($hijos as $item) {
                if ($item->id === $omitId) continue;
                $tieneHijos = method_exists($item, 'getItems') && count($item->getItems()) > 0;
                $icono = ($item->isGroup || $tieneHijos) ? '📁 ' : '';
                $opciones[$item->id] = $icono . $item->id;
            }

            $opciones['__seleccionar__'] = '✅ Seleccionar este nodo';

            if (!empty($pila)) {
                $opciones['__atras__'] = '🔙 Regresar';
            }

            if ($this->soloGrupos && method_exists($nodoActual, 'makeGroup')) {
                $opciones['__crear_grupo__'] = '➕ Crear nuevo grupo aquí';
            }
        } else {
            $coleccion = $this->soloGrupos
                ? $rutas->filter(fn($r) => $r->isGroup)
                : $rutas;

            foreach ($coleccion as $ruta) {
                if ($ruta->id === $omitId) continue;
                $tieneHijos = method_exists($ruta, 'getItems') && count($ruta->getItems()) > 0;
                $icono = ($ruta->isGroup || $tieneHijos) ? '📁 ' : '';
                $opciones[$ruta->id] = $icono . $ruta->id;
            }

            $opciones['__seleccionar__'] = '✅ Seleccionar una raíz';
            $opciones['__salir__'] = '🚪 Salir';

            if ($this->soloGrupos) {
                $opciones['__crear_grupo__'] = '➕ Crear grupo raíz';
            }
        }

        $breadcrumb = collect($pila)->pluck('id')
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

            '__atras__' => self::make($this->soloGrupos)->navegar(
                $rutas,
                array_pop($pila),
                $pila,
                $omitId
            ),

            '__crear_grupo__' => $this->crearGrupo($rutas, $nodoActual, $pila, $omitId),

            default => self::make($this->soloGrupos)->navegar(
                $rutas,
                ($nodoActual ? collect($nodoActual->getItems()) : $rutas)
                    ->firstWhere(fn($r) => $r->id === $seleccion),
                array_merge($pila, [$nodoActual]),
                $omitId
            ),
        };
    }



    private function crearGrupo(Collection $rutas, ?FpFEntityInterface $nodoActual, array $pila, ?string $omitId): ?string
    {
        $nombreGrupo = text('🆕 Ingresa el ID para el nuevo grupo:');

        if (empty($nombreGrupo)) {
            return $this->navegar($rutas, $nodoActual, $pila, $omitId);
        }

        $grupo = $nodoActual
            ? get_class($nodoActual)::makeGroup($nombreGrupo)
            : get_class($rutas->first())::makeGroup($nombreGrupo);

        if ($nodoActual && method_exists($grupo, 'setParentId')) {
            $grupo->setParentId($nodoActual->id);
            $nodoActual->addItem($grupo);
        } else {
            $rutas->push($grupo);
        }

        $grupo->save(parent: $nodoActual);

        return self::make($this->soloGrupos)->navegar(
            $rutas,
            $grupo,
            array_merge($pila, [$nodoActual]),
            $omitId
        );
    }
}
