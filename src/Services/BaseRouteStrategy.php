<?php

namespace Fp\FullRoute\Services;

use Fp\FullRoute\Clases\FullRoute;
use Fp\FullRoute\Contracts\RouteStrategyInterface;
use Fp\FullRoute\Services\RouteContentManager;
use Fp\FullRoute\Services\RouteValidationService;
use Fp\FullRoute\Services\Transformer\TransformerContext;
use Illuminate\Support\Collection;

/**
 * Clase abstracta BaseRouteStrategy que implementa la interfaz RouteStrategyInterface.
 * Proporciona una base para las estrategias de rutas que manejan el contenido de las rutas.
 */
abstract class BaseRouteStrategy implements RouteStrategyInterface
{
    protected RouteContentManager $fileManager;
    protected ?TransformerContext $transformer;

    /**
     * Constructor de la clase RouteStrategyFile.
     *
     * @param RouteContentManager $fileManager El gestor de contenido de rutas.
     */

    public function __construct(
        RouteContentManager $fileManager,
        ?TransformerContext $transformer = null
    ) {
        $this->fileManager = $fileManager;
        $this->transformer = $transformer ?? TransformerContext::make(
            contentManager: $fileManager,
            type: $this->getTransformerType()
        );
    }

    /**
     * Crea una nueva instancia de RouteStrategyFile.
     *
     * @param RouteContentManager $fileManager El gestor de contenido de rutas.
     * @return self La nueva instancia de RouteStrategyFile.
     */
    public static function make(
        RouteContentManager $fileManager,
        ?TransformerContext $transformer = null
    ): self {
        return new self($fileManager, $transformer);
    }

    abstract protected function getTransformerType(): string;

    // MÉTODOS QUE DEPENDEN DEL TIPO DE ESTRUCTURA
    abstract public function getAllRoutes(): Collection;
    abstract public function getAllFlattenedRoutes(?Collection $routes = null): Collection;


    /**
     * Agrega una ruta al archivo de rutas.
     *
     * @param FullRoute $route La ruta a agregar.
     * @throws \Exception Si la ruta no es válida o si ocurre un error al insertar la ruta.
     */
    public function addRoute(FullRoute $route, string|FullRoute|null $parent): void
    {
        $routes = $this->getAllRoutes();
        if ($parent instanceof FullRoute) {
            $parentId = $parent->getId();
        }
        if ($parentId ?? null) {
            $updatedRoutes = $this->addRouteRecursive($routes, $route, $parentId);
        } else {
            $routes->push($route); // Agrega al nivel raíz si no se especifica padre
            $updatedRoutes = $routes;
        }

        $this->transformer
            ->setCollectionRoutes($updatedRoutes)
            ->transformAndWrite();
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

        $this->transformer
            ->setCollectionRoutes($removeRoutes)
            ->setType('file_tree')
            ->transformAndWrite();
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

        if ($routes->isEmpty()) {
            return null; // No se encontraron rutas
        }
        // Si se encontraron rutas, se transforman a una colección de FullRoute
        return $routes->map(function ($route) {
            return new FullRoute(
                id: $route->getId(),
                urlName: $route->getUrlName(),
                url: $route->getUrl(),
                params: $route->getParams(),
                parentId: $route->getParentId(),
                childrens: $route->getChildrens()
            );
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
            if ($route->getParentId() === null)
                $flag = true;
            else
                $routeId = $route->getParentId();
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
    public function moveRoute(FullRoute $fromRoute, FullRoute $toRoute): void {}



    protected function addRouteRecursive(Collection $routes, FullRoute $newRoute, string $parentId): Collection
    {
        return $routes->map(function ($route) use ($newRoute, $parentId) {
            if ($route->id === $parentId) {
                $newRoute->setLevel($route->getLevel() + 1);
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


    protected function removeRouteRecursive(collection $routes, string $routeId): Collection
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
