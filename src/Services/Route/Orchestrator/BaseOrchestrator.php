<?php

namespace Fp\FullRoute\Services\Route\Orchestrator;

use Illuminate\Support\Collection;
use Fp\FullRoute\Contracts\OrchestratorInterface;
use Fp\FullRoute\Contracts\FpEntityInterface;
use Fp\FullRoute\Entities\FpBaseEntity;
use Fp\FullRoute\Services\Route\Orchestrator\VarsOrchestratorTrait;
use Fp\FullRoute\Services\Route\RouteContext;


abstract class BaseOrchestrator implements OrchestratorInterface
{
    use VarsOrchestratorTrait;

    // Context and Entity Map
    protected array $entityMap = [];
    protected array $contexts = [];

    abstract protected function prepareContext(array $config): mixed;

    abstract protected function loadFromConfig(): void;

    abstract public function getDefaultContext(): ?RouteContext;

    public function __construct()
    {
        $this->loadFromConfig();
    }


    // ALL ENTITIES

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
        if (isset($this->entityMap[$id]))
            return $this->entityMap[$id]->findRoute($id);
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

    /**
     * Rewrite all routes in all contexts.
     *
     * @return self
     */
    public function rewriteAllContext(): self
    {
        foreach ($this->contexts as $context)
            $context->rewriteAllRoutes();
        return $this;
    }
}
