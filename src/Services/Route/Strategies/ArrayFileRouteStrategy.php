<?php

namespace Fp\FullRoute\Services\Route\Strategies;

use Illuminate\Support\Collection;

class ArrayFileRouteStrategy extends BaseRouteStrategy
{

    public function getTransformerType(): string
    {
        return 'file_array';
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
}
