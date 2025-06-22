<?php

namespace FPJ\RoutingKit\Features\InteractiveFeature;

use FPJ\RoutingKit\Contracts\FPJEntityInterface;
use Illuminate\Support\Collection;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class FPJTreeNavigator
{
    private bool $soloGrupos = false;
    private ?FPJEntityInterface $nodoInicial = null;
    private array $pilaNavegacion = [];
    private ?string $idOmitir = null;
    private string $etiquetaSeleccion = "Selecciona una ruta raíz";
    private Collection $rutasDisponibles;
    private bool $permitirSeleccionarRaiz = true; // ¡NUEVA PROPIEDAD!

    public function __construct(Collection|array $rutas)
    {
        $this->rutasDisponibles = is_array($rutas) ? collect($rutas) : $rutas;
    }

    public static function make(Collection|array $rutas): self
    {
        return new self($rutas);
    }

    /**
     * Configura el navegador para mostrar solo grupos.
     */
    public function soloGrupos($bool = true): self
    {
        $this->soloGrupos = (bool) $bool;
        return $this;
    }

    /**
     * Establece el nodo inicial desde el cual comenzar la navegación.
     */
    public function desdeNodo(FPJEntityInterface $nodo): self
    {
        $this->nodoInicial = $nodo;
        return $this;
    }

    /**
     * Establece el ID de un elemento a omitir en la navegación.
     */
    public function omitirId(?string $id): self
    {
        $this->idOmitir = $id;
        return $this;
    }

    /**
     * Personaliza la etiqueta que se mostrará al usuario para la selección.
     */
    public function conEtiqueta(string $label): self
    {
        $this->etiquetaSeleccion = $label;
        return $this;
    }

    /**
     * Permite o no seleccionar una ruta raíz en la navegación.
     * ¡NUEVO MÉTODO!
     */
    public function permitirSeleccionarRaiz(bool $permitir = true): self
    {
        $this->permitirSeleccionarRaiz = $permitir;
        return $this;
    }

    /**
     * Inicia el proceso de navegación interactiva.
     */
    public function navegar(): ?string
    {
        return $this->ejecutarNavegacion(
            $this->rutasDisponibles,
            $this->nodoInicial,
            $this->pilaNavegacion,
            $this->idOmitir,
            $this->etiquetaSeleccion
        );
    }

    /**
     * Lógica recursiva interna para la navegación.
     * Este método es privado y se llama desde 'navegar()'.
     */
    private function ejecutarNavegacion(
        Collection $rutas,
        ?FPJEntityInterface $nodoActual,
        array $pila,
        ?string $omitId,
        string $label
    ): ?string {
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
            // Lógica para la raíz
            $coleccion = $this->soloGrupos
                ? $rutas->filter(fn($r) => $r->isGroup)
                : $rutas;

            foreach ($coleccion as $ruta) {
                if ($ruta->id === $omitId) continue;
                $tieneHijos = method_exists($ruta, 'getItems') && count($ruta->getItems()) > 0;
                $icono = ($ruta->isGroup || $tieneHijos) ? '📁 ' : '';
                $opciones[$ruta->id] = $icono . $ruta->id;
            }

            if ($this->soloGrupos) {
                $opciones['__crear_grupo__'] = '➕ Nuevo grupo';
            }

            if ($this->permitirSeleccionarRaiz) {
                $opciones['__seleccionar__'] = '✅ Seleccionar(raiz)';
            }
            $opciones['__salir__'] = '🚪 Salir';

            
        }

        $breadcrumb = collect($pila)->pluck('id')
            ->push(optional($nodoActual)->id)
            ->filter()
            ->implode('/');

        $seleccion = select(
            label: $breadcrumb ? $label ." Actual: /{$breadcrumb}" : $label ." Actual (raiz): /",
            options: $opciones
        );

        return match ($seleccion) {
            '__salir__' => exit("🚪 Saliendo del navegador interactivo.\n"),

            // Modificamos esta parte para que solo devuelva null si no se permite seleccionar la raíz
            '__seleccionar__' => $nodoActual?->id ?? ($this->permitirSeleccionarRaiz ? null : $this->ejecutarNavegacion($rutas, $nodoActual, $pila, $omitId, $label)),

            '__atras__' => $this->ejecutarNavegacion(
                $rutas,
                array_pop($pila),
                $pila,
                $omitId,
                $label
            ),

            '__crear_grupo__' => $this->crearGrupo($rutas, $nodoActual, $pila, $omitId, $label),

            default => $this->ejecutarNavegacion(
                $rutas,
                ($nodoActual ? collect($nodoActual->getItems()) : $rutas)
                    ->firstWhere(fn($r) => $r->id === $seleccion),
                array_merge($pila, [$nodoActual]),
                $omitId,
                $label
            ),
        };
    }

    private function crearGrupo(Collection $rutas, ?FPJEntityInterface $nodoActual, array $pila, ?string $omitId, string $label): ?string
    {
        $nombreGrupo = text('🆕 Ingresa el ID para el nuevo grupo:');

        if (empty($nombreGrupo)) {
            return $this->ejecutarNavegacion($rutas, $nodoActual, $pila, $omitId, $label);
        }

        // Determinar la clase para crear el grupo
        $claseBase = null;
        if ($nodoActual) {
            $claseBase = get_class($nodoActual);
        } elseif ($rutas->isNotEmpty()) {
            $claseBase = get_class($rutas->first());
        }

        if (!$claseBase || !method_exists($claseBase, 'makeGroup')) {
            // Manejar error si no se puede determinar la clase o no tiene makeGroup
            // Podrías lanzar una excepción o simplemente regresar a la navegación
            return $this->ejecutarNavegacion($rutas, $nodoActual, $pila, $omitId, $label);
        }

        $grupo = $claseBase::makeGroup($nombreGrupo);

        if ($nodoActual && method_exists($grupo, 'setParentId')) {
            $grupo->setParentId($nodoActual->id);
            $nodoActual->addItem($grupo);
        } else {
            $rutas->push($grupo);
        }

        // Asegúrate de que el método save sea llamado correctamente.
        // Asumiendo que save puede necesitar el padre para la persistencia.
        if (method_exists($grupo, 'save')) {
            $grupo->save(parent: $nodoActual);
        }

        return $this->ejecutarNavegacion(
            $rutas,
            $grupo,
            array_merge($pila, [$nodoActual]),
            $omitId,
            $label
        );
    }
}