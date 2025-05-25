<?php

namespace Fp\FullRoute\Services;

use Fp\FullRoute\Clases\FullRoute;
use Fp\FullRoute\Services\RouteValidationService;
use Fp\FullRoute\Services\RouteContentManager;

use Illuminate\Support\Collection;
use Fp\FullRoute\Contracts\RouteStrategyInterface;
use Fp\FullRoute\Traits\AuxiliarFilesTrait;

class RouteStrategyFileUnit implements RouteStrategyInterface
{
    use AuxiliarFilesTrait;

    protected RouteContentManager $fileManager;

    /**
     * Constructor de la clase RouteStrategyFile.
     *
     * @param RouteContentManager $fileManager El gestor de contenido de rutas.
     */
    public function __construct(RouteContentManager $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    /**
     * Crea una nueva instancia de RouteStrategyFile.
     *
     * @param RouteContentManager $fileManager El gestor de contenido de rutas.
     * @return self La nueva instancia de RouteStrategyFile.
     */
    public static function make(RouteContentManager $fileManager): self
    {
        return new self($fileManager);
    }

    /**
     * Agrega una ruta al archivo de rutas.
     *
     * @param FullRoute $route La ruta a agregar.
     * @throws \Exception Si la ruta no es válida o si ocurre un error al insertar la ruta.
     */
    public function addRoute(FullRoute $route, string|FullRoute|null $parent): void
    {
        $routes = $this->getAllRoutes();
        if ($parent instanceof FullRoute)
            $parentId = $parent->getId();


        if ($parentId ?? null) {
            $updatedRoutes = $this->addRouteRecursive($routes, $route, $parentId);
        } else {
            $routes->push($route); // Agrega al nivel raíz si no se especifica padre
            $updatedRoutes = $routes;
        }

        Transformer::make($this->fileManager, $updatedRoutes)
            ->reWriteContent();
    }

    /**
     * Obtiene todas las rutas del archivo de rutas.
     *
     * @return Collection Colección de rutas.
     */
    public function getAllRoutes(): Collection
    {
        $routes = $this->getAllFlattenedRoutes();

        // Paso 1: Índice por ID
        $itemsById = $routes->keyBy(fn($item) => $item->getId());
        //dd("itemsById", $itemsById);
        // Paso 2: Contenedor de nodos raíz
        $tree = [];

        foreach ($itemsById as $item) {
            if (isset($item->parentId) && $item->parentId !== '')
                // Si tiene padre, se agrega a sus hijos
                $itemsById[$item->parentId]->addChild($item);
            else
                // Si no tiene padre, es un nodo raíz
                $tree[] = $item;
        }
        // dd($tree);
        $tree = collect($tree);
        // Establecer fullUrlName y fullUrl recursivamente
        $this->setFullUrls($tree);
        //dd($tree);
        return $tree;
    }

    private function setFullUrls(Collection $routes, string $parentFullName = '', string $parentFullUrl = '', int $level = 0): void
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

    /**
     * Busca una ruta por su ID.
     *
     * @param string $routeId El ID de la ruta a buscar.
     * @return FullRoute|null La ruta encontrada o null si no se encuentra.
     */
    public function findRoute(string $routeId): ?FullRoute
    {
        return $this->getAllFlattenedRoutes()
            ->first(fn(FullRoute $route) => $route->getId() === $routeId);
    }


    /**
     * Busca una ruta por un nombre de parametro y su valor y retorna una colección de coincidencias.     
     * @param string $routeName el nombre del parametro a buscar.
     * @param string $value el valor del parametro a buscar.
     * @return Collection La ruta encontrada o null si no se encuentra.
     */

    public function findByParamName(string $paramName, string $value): ?Collection
    {
        // se hace una busqueda de arbol de rutas
        $routes = $this->getAllRoutes();
        // se busca la ruta por el nombre del parametro y su valor
        $routes = $routes->filter(function (FullRoute $route) use ($paramName, $value) {
            return $route->getParam($paramName) === $value;
        });
    }

    /**
     * Busca una ruta por su nombre.
     *
     * @param string $routeName El nombre de la ruta a buscar.
     * @return FullRoute|null La ruta encontrada o null si no se encuentra.
     */
    public function findByRouteName(string $routeName): ?FullRoute
    {
        return $this->getAllFlattenedRoutes()
            ->first(fn(FullRoute $route) => $route->getUrlName() === $routeName);
    }


    /**
     * Obtiene todas las rutas aplanadas. (OPTIMIZAR O MODIFICAR LA LOGICA DE BUSQUEDA ACTUALMENTE ES DEMASIADO COStoso)
     *
     * @param Collection $routes Colección de rutas.
     * @return Collection Colección de rutas aplanadas.
     */
    public function getAllFlattenedRoutes(?Collection $routes = null): Collection
    {
        return $routes ?? collect($this->fileManager->getContents());
    }


    public function getBreadcrumbs(string|FullRoute $routeId): Collection
    {
        if ($routeId instanceof FullRoute) {
            $routeId = $routeId->getId();
        }

        $flattened = $this->getAllFlattenedRoutes();
        $byId = $flattened->keyBy(fn($route) => $route->getId());

        $breadcrumb = [];

        $flag = false;
        while (!$flag) {
            $route = $byId[$routeId];
            array_unshift($breadcrumb, $route); // prepend to breadcrumb
            if ($route->getParentId() === null) {
                $flag = true;
            } else {
                $routeId = $route->getParentId();
            }
        }

        return collect($breadcrumb);
    }


    /**
     * Verifica si una ruta existe por su ID.
     *
     * @param string $routeId El ID de la ruta a verificar.
     * @return bool true si la ruta existe, false en caso contrario.
     */
    public function exists(string $routeId): bool
    {
        return $this->findRoute($routeId) !== null;
    }



    /**
     * Mueve una ruta de un lugar a otro.
     *
     * @param FullRoute $fromRoute La ruta de origen.
     * @param FullRoute $toRoute La ruta de destino.
     * @throws \Exception Si la ruta no es válida o si ocurre un error al mover la ruta.
     */
    public function moveRoute(FullRoute $fromRoute, FullRoute $toRoute): void
    {
        RouteValidationService::make()
            ->validateMoveRoute($fromRoute, $this->getAllRoutes());

        $file = $this->fileManager->getContentsString();
        $fromRouteId = $fromRoute->getId();

        $pattern = $this->getPattern($fromRouteId);

        if (!preg_match($pattern, $file, $matches)) {
            throw new \Exception("No se encontró la ruta con ID {$fromRouteId}");
        }

        $bloque = $matches[0];
        // eliminar los espacios del inicio del bloque
        // asignar el espacion al final del bloqu
        $bloque = preg_replace('/^\s+/m', '', $bloque);
        // quitar si existe una coma al inicio
        $bloque = preg_replace('/^,/', '', $bloque);
        $bloque = "\n" . $bloque . "\n";

        $this->removeRoute($fromRouteId);

        $this->insertRouteContent($toRoute, $bloque);
    }


    /**
     * Elimina una ruta por su ID.
     *
     * @param string $routeId El ID de la ruta a eliminar.
     * @throws \Exception Si la ruta no es válida o si ocurre un error al eliminar la ruta.
     */
    public function removeRoute(string $routeId): void
    {
        $routes = $this->getAllRoutes();
        $removeRoutes = $this->removeRouteRecursive($routes, $routeId);

        Transformer::make($this->fileManager, $removeRoutes)
            ->reWriteContent();
    }

    private function addRouteRecursive(Collection $routes, FullRoute $newRoute, string $parentId): Collection
    {
        return $routes->map(function ($route) use ($newRoute, $parentId) {
            if ($route->id === $parentId) {
                $route->childrens = array_merge(
                    $route->childrens ?? [],
                    [$newRoute]
                );
            }

            if (!empty($route->childrens)) {
                $route->childrens = $this->addRouteRecursive(collect($route->childrens), $newRoute, $parentId)->toArray();
            }

            return $route;
        });
    }


    private function removeRouteRecursive(collection $routes, string $routeId): Collection
    {

        $result = [];

        foreach ($routes as $route) {

            if ($route->id === $routeId) {
                // Saltamos este nodo, lo eliminamos con todos sus hijos
                continue;
            }


            // Si el nodo tiene hijos, procesarlos recursivamente
            if (!empty($route->childrens)) {
                $route->childrens = $this->removeRouteRecursive(collect($route->childrens), $routeId)->toArray();
            }

            $result[] = $route;
        }

        return collect($result);
    }
}
