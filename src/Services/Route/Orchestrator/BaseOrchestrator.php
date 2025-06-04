<?php

namespace Fp\FullRoute\Services\Route\Orchestrator;

use Illuminate\Support\Collection;
use Fp\FullRoute\Contracts\OrchestratorInterface;
use Fp\FullRoute\Contracts\FpEntityInterface;
use Fp\FullRoute\Entities\FpBaseEntity;
use Fp\FullRoute\Services\Route\RouteContext;


abstract class BaseOrchestrator implements OrchestratorInterface
{
    protected array $entityMap = [];
    protected array $contexts = [];

    protected ?Collection $treeAllEntitys = null;
    protected ?Collection $flattenedAllEntitys = null;
    protected ?Collection $allFlattenedWhitChilds = null;


    abstract protected function prepareContext(array $config): mixed;

    abstract protected function loadFromConfig(): void;

    abstract public function getDefaultContext(): ?RouteContext;

    public function __construct()
    {
        $this->loadFromConfig();
    }


    /**
     * Save permanently the entity, optionally setting a parent.
     *
     * @param string|FpEntityInterface|null $parent
     * @return self
     */
    public function save(FpEntityInterface $new, string|FpEntityInterface|null $parent = null): FpEntityInterface
    {

        // Si el valor es null, se interpreta como ruta raíz (sin padre)
        if ($parent instanceof FpEntityInterface)
            $parentId = $parent->getId();
        else
            $parentId = $parent;

        $new->setParentId($parentId);
        //dd($parent);
        // Buscar el contexto del padre solo si hay un ID
        $context = $parent !== null
            ? $this->findContextById($parentId)
            : $this->getDefaultContext(); // Si no hay padre, usa el primer contexto disponible (opcional)

        if (!$context) {
            throw new \RuntimeException(
                $parentId !== null
                    ? "No se encontró un contexto que contenga la ruta padre: $parentId"
                    : "No hay contextos disponibles para agregar la ruta raíz"
            );
        }
        // Agregar la nueva ruta al contexto
        $context->addRoute($new, $parentId);
        //dd($context->getAllRoutes());
        // dd($context->getAllRoutes());
        // Actualizar el índice de rutas
        $this->entityMap[$new->getId()] = $context;

        return $new;
    }

    /**
     * Delete the entity permanently.
     *
     * 
     * @return bool
     */
    public function delete(string|FpEntityInterface $entity): bool
    {

        if ($entity instanceof FpEntityInterface)
            $entityId = $entity->getId();
        else
            $entityId = $entity;

        $context = $this->findContextById($entityId);

        if ($context) {
            $context->removeRoute($entityId);
            unset($this->entityMap[$entityId]); // mantener limpio el índice
        } else {
            throw new \RuntimeException("No se encontró un contexto que contenga la entidad: $entity");
        }

        return true;
    }

    public function findContextById(string $routeId): ?RouteContext
    {
        return $this->entityMap[$routeId] ?? null;
    }

    /**
     * Find an entity by its ID.
     *
     * @param string $id
     * @return FpEntityInterface|null
     */
    public function findById(string $id): ?FpEntityInterface
    {
        //dd($this->entityMap[$id]->findRoute($id));
        // Buscar en el índice de rutas
        if (isset($this->entityMap[$id]))
            return $this->entityMap[$id]->findRoute($id);

        // Si no se encuentra, retornar null
        return null;
    }

    public function getBrothers(string|FpEntityInterface $entity): Collection
    {
        if ($entity instanceof FpEntityInterface)
            $id = $entity->getId();

        // Verifica si la ruta existe en el índice de rutas
        if (isset($this->entityMap[$id])) {
            $parentId = $this->allFlattenedWhitChilds[$id]->getParentId();
            if ($parentId !== null) {
                return collect($this->allFlattenedWhitChilds[$parentId]->getChildrens());
            }
        }

        return collect(); // Retorna una colección vacía si no hay hermanos
    }


    public function findByIdWithChilds(string $id): ?FpEntityInterface
    {
        // Buscar en el índice de rutas
        if (isset($this->allFlattenedWhitChilds[$id]))
            return $this->allFlattenedWhitChilds[$id];

        // Si no se encuentra, retornar null
        return null;
    }

    /**
     * Find an entity by its ID, or throw an exception if not found.
     *
     * @param string $id
     * @return FpEntityInterface
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByParamName(string $paramName, string $value): ?Collection
    {
        return collect();
    }

    /**
     * get all entities.
     * @return Collection
     */
    public function all(): Collection
    {
        if ($this->treeAllEntitys === null) {
            $allFlattened = $this->getAllFlattenedRoutesGlobal();
            $this->treeAllEntitys = $this->buildTreeFromFlattened($allFlattened);
        }
        return $this->treeAllEntitys;
    }

    /**
     * Get all entities grouped by file.
     *
     * @return Collection
     */
    public  function getAllsByFile(string $filePath): Collection
    {
        return collect();
    }


    /**
     * Get all entities grouped by file, flattened.
     *
     * @return Collection
     */
    public  function getAllsByFileFlattened(): Collection
    {
        return collect();
    }


    /**
     * Get all entities in a flattened structure.
     *
     * @return Collection
     */
    public  function allFlattened(): Collection
    {
        return $this->getAllFlattenedRoutesGlobal();
    }

    /**
     * Get all entities in a tree structure.
     *
     * @return Collection
     */
    public  function allInTree(): Collection
    {
        return collect();
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


    // Utility Methods

    /**
     * Check if an entity exists by its ID.
     *
     * @param string $id
     * @return bool
     */
    public  function exists(string $id): bool
    {
        // Verifica si la ruta existe en el índice de rutas
        return isset($this->entityMap[$id]);
    }

    /**
     * Check if an entity is a root entity.
     *
     * @return bool
     */
    public  function registers(): void
    {
        return;
    }

    /**
     * Check if an entity is a child of another entity.
     *
     * @param string $id
     * @return bool
     */
    public function isChild(string|FpEntityInterface $entity): bool
    {
        if ($entity instanceof FpEntityInterface)
            $id = $entity->getId();

        // Verifica si la ruta existe en el índice de rutas
        return isset($this->entityMap[$id]) && $this->entityMap[$id]->getParentId() !== null;
    }


    /**
     * returns the parent entity of the current entity.
     *
     * @param string $id
     * @return bool
     */
    public function parent(string|FpEntityInterface $parent): FpEntityInterface
    {
        if ($parent instanceof FpEntityInterface)
            $id = $parent->getId();

        // Verifica si la ruta existe en el índice de rutas
        if (isset($this->entityMap[$id])) {
            return $this->entityMap[$id]->getParent();
        }

        throw new \RuntimeException("No se encontró un contexto que contenga la ruta: $id");
    }

    protected function buildTreeFromFlattened(Collection $flat): Collection
    {
        // Mapa temporal de ID a copias de entidades
        $cloned = collect();

        // Primero clonamos todas las entidades
        foreach ($flat as $entity) {
            $cloned->put($entity->getId(), clone $entity);
        }

        $tree = collect();

        foreach ($cloned as $id => $entity) {
            $parentId = $entity->getParentId();

            if ($parentId !== null && $cloned->has($parentId)) {
                $parent = $cloned->get($parentId);
                $parent->addChild($entity);
            } else {
                $tree->push($entity);
            }
        }
        $this->allFlattenedWhitChilds = $cloned;

        return $tree;
    }

    public function getAllFlattenedWhitChilds(): ?Collection
    {
        if ($this->allFlattenedWhitChilds === null) {
            $this->getAllFlattenedRoutesGlobal();
        }

        return $this->allFlattenedWhitChilds;
    }


    public function getAllFlattenedRoutesGlobal(): ?Collection
    {
        if ($this->flattenedAllEntitys === null) {
            # echo "|||||||||||||>...\n";
            $this->flattenedAllEntitys = collect();

            foreach ($this->contexts as $context) {
                $this->flattenedAllEntitys = $this->flattenedAllEntitys->merge($context->getAllFlattenedRoutes());
            }
        }

        # echo "|||||||||||||<...\n";

        // dd($this->contexts); // <-- Elimina esto si ya no necesitas debug.
        return $this->flattenedAllEntitys;
    }

    /**
     * Get the breadcrumbs for the entity. in the tree structure.
     *
     * @return Collection
     */
    public function getBreadcrumbs(string|FpEntityInterface $entity): Collection
    {
        if ($entity instanceof FpEntityInterface)
            $id = $entity->getId();


        $flattened = $this->getAllFlattenedRoutesGlobal();
        $byId = $flattened->keyBy(fn($entity) => $entity->getId());

        $breadcrumb = [];

        $flag = false;
        while (!$flag) {
            $entity = $byId[$id];
            array_unshift($breadcrumb, $entity); // prepend to breadcrumb
            if ($entity->getParentId() === null)
                $flag = true;
            else
                $id = $entity->getParentId();
        }

        return collect($breadcrumb);
    }
    /**
     * Move the entity to a new parent.
     *
     * @return FpEntityInterface
     */
    public function moveTo(string|FpEntityInterface $parent): self
    {
        return $this;
    }


    /**
     * Get the ID of the entity.
     *
     * @return string
     */
    public function getParent(): ?FpEntityInterface
    {
        if ($this->getParentId() === null) {
            return null; // No tiene padre
        }

        $parent = $this->findById($this->getParentId());
        if ($parent) {
            return $parent;
        }

        throw new \RuntimeException("No se encontró un padre para la ruta: {$this->getParentId()}");
    }

    /**
     * add a child to the current entity.
     *
     * @return self
     */
    public function addChild(FpEntityInterface $father, FpEntityInterface $child): FpEntityInterface
    {
        return $father->addChild($child);
    }


    public function rewriteAllContext(): self
    {
        // Reescribe todos los contextos, si es necesario
        foreach ($this->contexts as $context) {
            $context->rewriteAllRoutes();
        }
        return $this;
    }
}
