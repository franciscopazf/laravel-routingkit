<?php

namespace Fp\FullRoute\Services\Route\Strategies;

use Fp\FullRoute\Clases\FullRoute;
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
     * Obtiene todas las rutas aplanadas desde una jerarquía de rutas.
     *
     * @param Collection|null $routes Colección de rutas (si no se especifica, se usan todas).
     * @return Collection Colección de rutas aplanadas.
     */
    public function getAllFlattenedRoutes(?Collection $routes = null): Collection
    {
        if ($routes === null) {
            $routes = $this->getAllRoutes();
        }

        $flattened = collect();

        $this->flattenRoutesRecursive($routes, $flattened);
        //dd($flattened);
        return $flattened;
    }

    /**
     * Función auxiliar recursiva para aplanar el árbol de rutas.
     *
     * @param Collection $routes Rutas actuales a procesar.
     * @param Collection $flattened Colección donde se almacenan las rutas aplanadas.
     * @return void
     */
    private function flattenRoutesRecursive(Collection $routes, Collection &$flattened): void
    {
        foreach ($routes as $route) {
            $flattened->push($route);

            $children = collect($route->getChildrens());

            if ($children->isNotEmpty()) {
                $this->flattenRoutesRecursive($children, $flattened);
            }
            // una ves recorridos los hijos eliminarlos :)
            $route->setChildrens([]);
        }
    }
}
