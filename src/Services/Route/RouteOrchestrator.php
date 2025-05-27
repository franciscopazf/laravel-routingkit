<?php

namespace Fp\FullRoute\Services\Route;

use Fp\FullRoute\Clases\FullRoute;
use Fp\FullRoute\Services\Route\Strategies\RouteStrategyFactory;
use Illuminate\Support\Collection;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;

class RouteOrchestrator
{
    /** @var array<string, RouteContext> */
    protected array $routeMap = [];

    protected array $contexts = [];

    public function __construct()
    {
        $this->loadFromConfig();
    }

    public static function make(): self
    {
        return new self();
    }


    public function prepareContext(array $contextData): RouteContext
    {
        // Crear un nuevo contexto con la estrategia adecuada
        $context = RouteStrategyFactory::make(
            $contextData['support_file'],
            $contextData['path']
        );
        $this->contexts[] = $context;

        // Devolver el contexto creado
        return $context;
    }

    public function getDefaultContext(): ?RouteContext
    {
        // Retorna el primer contexto si existe, o null si no hay contextos
        $position = config('fproute.defaul_file_path_position', 0);
        return $this->contexts[$position] ?? null;
    }

    public function existsRoute(string $routeId): bool
    {
        // Verifica si la ruta existe en el índice de rutas
        return isset($this->routeMap[$routeId]);
    }


    protected function loadFromConfig(): void
    {

        $configs = config('fproute.routes_file_path');

        foreach ($configs as $config) {
            $context = $this->prepareContext($config);
            $this->contexts[] = $context;

            $flatRoutes = $context->getAllFlattenedRoutes($context->getAllRoutes());

            foreach ($flatRoutes as $route)
                $this->routeMap[$route->getId()] = $context;
        }
    }

    public function addRoute(FullRoute $newRoute, FullRoute|string|null $parenRoute = null): void
    {
        // Si el valor es null, se interpreta como ruta raíz (sin padre)

        if ($parenRoute instanceof FullRoute) {
            $parenRouteId = $parenRoute->getId();
            $newRoute->setParentId($parenRoute->getId());
        }
        else {
            $parenRouteId = $parenRoute; // Puede ser un string o null
            $newRoute->setParentId($parenRouteId);
        }
        $newRoute->setParentId($parenRoute);



        // Buscar el contexto del padre solo si hay un ID
        $context = $parenRoute !== null 
            ? $this->findContextByRouteId($parenRouteId)
            : $this->getDefaultContext(); // Si no hay padre, usa el primer contexto disponible (opcional)

        if (!$context) {
            throw new \RuntimeException(
                $parenRouteId !== null
                    ? "No se encontró un contexto que contenga la ruta padre: $parenRouteId"
                    : "No hay contextos disponibles para agregar la ruta raíz"
            );
        }
        // Agregar la nueva ruta al contexto
        $context->addRoute($newRoute, $parenRouteId);

       // dd($context->getAllRoutes());
        // Actualizar el índice de rutas
        $this->routeMap[$newRoute->getId()] = $context;
    }


    public function findRoute(string $routeId): FullRoute|null
    {
        // Buscar en el índice de rutas
        if (isset($this->routeMap[$routeId]))
            return $this->routeMap[$routeId]->findRoute($routeId);


        // Si no se encuentra, retornar null
        return null;
    }

    /**
     * Obtiene todos los contenidos de las rutas combinadas de todos los contextos.
     */
    public function getAllRoutes(): Collection
    {
        $rutasmerge = collect($this->contexts)
            ->flatMap(function ($context) {
                return $context->getAllRoutes(); // asumiendo que devuelve una Collection
            })
            ->values(); // opcional, para reindexar
        // dd($rutasmerge);
        return $rutasmerge;
    }

    /**
     * Devuelve todos los contextos individuales por si se quiere operar sobre ellos.
     */
    public function getContexts(): array
    {
        return $this->contexts;
    }

    public function removeRoute(string $routeId): void
    {
        $context = $this->findContextByRouteId($routeId);

        if ($context) {
            $context->removeRoute($routeId);
            unset($this->routeMap[$routeId]); // mantener limpio el índice
        } else {
            throw new \RuntimeException("No se encontró un contexto que contenga la ruta: $routeId");
        }
    }

    public function findContextByRouteId(string $routeId): ?RouteContext
    {
        return $this->routeMap[$routeId] ?? null;
    }
}
