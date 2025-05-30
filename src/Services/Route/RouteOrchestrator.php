<?php

namespace Fp\FullRoute\Services\Route;

use Fp\FullRoute\Clases\FullRoute;
use Fp\FullRoute\Services\Route\Strategies\RouteStrategyFactory;
use Illuminate\Support\Collection;
use Fp\FullRoute\Services\Route\Strategies\TreeFileRouteStrategy;
use Fp\FullRoute\Services\Route\Strategies\RouteContentManager;

use League\CommonMark\Extension\CommonMark\Node\Inline\Code;

class RouteOrchestrator
{
    /** @var array<string, RouteContext> */
    protected array $routeMap = [];

    protected array $contexts = [];

    public function __construct()
    {
        echo "Inicializando el Orquestador de Rutas...";
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
        //dd($this->contexts);
        foreach ($configs as $config) {
            $context = $this->prepareContext($config);
            $this->contexts[] = $context;

            $flatRoutes = $context->getAllFlattenedRoutes();
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
        } else {
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

    public function getBreadcrumbs(string|FullRoute $routeId): Collection
    {
        if ($routeId instanceof FullRoute) {
            $routeId = $routeId->getId();
        } 

        $flattened = $this->getAllFlattenedRoutesGlobal();
        $byId = $flattened->keyBy(fn($route) => $route->getId());

        $breadcrumb = [];

        $flag = false;
        while (!$flag) {
            $route = $byId[$routeId];
            array_unshift($breadcrumb, $route); // prepend to breadcrumb
            if ($route->getParentId() === null)
                $flag = true;
            else
                $routeId = $route->getParentId();
        }

        return collect($breadcrumb);
    }

    public function rebuildContent(bool $force = false)
    {
        $routeConfigs = config('fproute.routes_file_path');

        if (!is_array($routeConfigs))
            return;

        foreach ($routeConfigs as $routeConfig) {
            if (isset($routeConfig['save_ass'], $routeConfig['path'])
                
            ) {
                $this->applyRouteStrategy($routeConfig);
            } else if ($force)
            {
                $context = $this->prepareContext($routeConfig);
                $context->rewriteAllRoutes();

            }
        }
    }

    /**
     * Aplica la estrategia de guardado de rutas según configuración.
     */
    private function applyRouteStrategy(array $routeConfig): void
    {
        $context = $this->prepareContext($routeConfig);
        $routes = $context->getAllRoutes();

        $strategy = RouteStrategyFactory::buildStrategy(
            $routeConfig['save_ass'],
            $routeConfig['path']
        );

        $context->setStrategy($strategy);
        $context->rewriteAllRoutes($routes);
    }


    public function getAllFlattenedRoutesGlobal(): Collection
    {
        return collect($this->contexts)
            ->flatMap(fn($context) => $context->getAllFlattenedRoutes())
            ->keyBy(fn($item) => $item->getId())
            ->values(); // Reindexar opcionalmente
    }

    public function rebuildGlobalTree(): Collection
    {
        $routes = $this->getAllFlattenedRoutesGlobal();

        // Paso 1: Índice por ID
        $itemsById = $routes->keyBy(fn($item) => $item->getId());

        // Paso 2: Contenedor de nodos raíz
        $tree = [];

        foreach ($itemsById as $item) {
            if ($item->getParentId() && $itemsById->has($item->getParentId())) {
                // Si tiene padre y existe en el índice, se agrega como hijo
                $itemsById[$item->getParentId()]->addChild($item);
            } else {
                // Nodo raíz si no tiene padre o el padre no está disponible
                $tree[] = $item;
            }
        }

        $tree = collect($tree);

        // Paso 3: Establecer fullUrl y fullUrlName de forma recursiva
        $this->setFullUrls($tree);

        return $tree;
    }

    /**
     * Obtiene todas las rutas del archivo de rutas.
     *
     * @return Collection Colección de rutas.
     */
    protected function setFullUrls(Collection $routes, string $parentFullName = '', string $parentFullUrl = '', int $level = 0): void
    {
        foreach ($routes as $route) {
            $fullName = $parentFullName ? $parentFullName . '.' . $route->getUrlName() : $route->getUrlName();
            $fullUrl = $parentFullUrl ? $parentFullUrl . '/' . $route->getUrl() : $route->getUrl();

            $route->setFullUrlName($fullName);
            $route->setFullUrl($fullUrl);
            $route->setLevel($level);

            if (!empty($route->getChildrens())) {
                $this->setFullUrls(collect($route->getChildrens()), $fullName, $fullUrl, $level + 1);
            }
        }
    }
}
