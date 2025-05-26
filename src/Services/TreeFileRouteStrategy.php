<?php

namespace Fp\FullRoute\Services;

use Fp\FullRoute\Clases\FullRoute;
use Fp\FullRoute\Contracts\RouteStrategyInterface;
use Fp\FullRoute\Services\RouteContentManager;
use Fp\FullRoute\Services\RouteValidationService;
use Fp\FullRoute\Services\Transformer\TransformerContext;
use Illuminate\Support\Collection;


class TreeFileRouteStrategy extends BaseRouteStrategy
{
    public function getTransformerType(): string
    {
        return 'file_tree';
    }

    /**
     * Obtiene todas las rutas del archivo de rutas.
     *
     * @return Collection Colección de rutas.
     */
    public function getAllRoutes(): Collection
    {
        $routes = $this->fileManager->getContents();
        // dd($routes);
        $setParentRefs = function ($node, $parent = null, $prefixName = '', $prefixUrl = '', $level = 0) use (&$setParentRefs) {
            if ($parent !== null) {
                $node->setParentId($parent->getId());
                $node->setParent($parent);
            }

            // Concatenar jerárquicamente
            $currentName = trim($prefixName . '.' . $node->getUrlName(), '.');
            $currentUrl  = rtrim($prefixUrl . '/' . ltrim($node->getUrl(), '/'), '/');

            // Asignar valores completos
            $node->fullUrlName = $currentName;
            $node->fullUrl     = '/' . ltrim($currentUrl, '/');
            $node->setLevel($level);

            foreach ($node->getChildrens() as $child) {
                $setParentRefs($child, $node, $currentName, $currentUrl, $level + 1);
            }

            return $node;
        };
        return collect($routes)->map(fn($route) => $setParentRefs($route));
    }
    /**
     * Obtiene todas las rutas aplanadas. (OPTIMIZAR O MODIFICAR LA LOGICA DE BUSQUEDA ACTUALMENTE ES DEMASIADO COStoso)
     *
     * @param Collection $routes Colección de rutas.
     * @return Collection Colección de rutas aplanadas.
     */
    public function getAllFlattenedRoutes(?Collection $routes = null): Collection
    {
        if ($routes === null)
            $routes = $this->getAllRoutes();

        return $routes->flatMap(function (FullRoute $route) {
            return collect([$route])->merge($this->getAllFlattenedRoutes(collect($route->getChildrens())));
        });
    }
}
