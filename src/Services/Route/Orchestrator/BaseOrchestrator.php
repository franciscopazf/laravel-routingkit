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
    protected array $entityMap = []; // Mapa de ID de entidad a su contexto
    protected array $contexts = []; // Todos los contextos disponibles
    protected array $activeContextKeys = []; // Las claves de los contextos actualmente 'cargados' para operaciones

    abstract protected function prepareContext(array $config): mixed;

    abstract protected function loadFromConfig(): void;

    abstract public function getDefaultContext(): ?RouteContext;

    public function __construct()
    {
        $this->loadAllAvailableContexts(); // Carga inicial de todos los contextos
    }

    /**
     * Carga todos los contextos disponibles desde la configuración.
     * Este es el método que inicializa `$this->contexts`.
     * @return void
     */
    protected function loadAllAvailableContexts(): void
    {
        $this->loadFromConfig(); // Asume que este método llena $this->contexts
        $this->activeContextKeys = array_keys($this->contexts); // Por defecto, todos los contextos están activos
        $this->rebuildEntityMap(); // Rellenar entityMap con todas las entidades iniciales
    }

    /**
     * Reconstruye el entityMap basándose en los contextos activos.
     * Se llama después de cambiar los contextos activos (loadContexts, excludeContexts, etc.).
     * @return void
     */
    protected function rebuildEntityMap(): void
    {
        $this->entityMap = [];
        foreach ($this->activeContextKeys as $key) {
            if (isset($this->contexts[$key])) {
                foreach ($this->contexts[$key]->getAllFlattenedRoutes() as $entity) {
                    $this->entityMap[$entity->getId()] = $this->contexts[$key];
                }
            }
        }
        // Invalida los cachés de VarsOrchestratorTrait para que se recarguen
        $this->treeAllEntitys = null;
        $this->flattenedAllEntities = null;
        $this->allFlattenedWhitChilds = null;
        $this->allExcludingContexts = null;
        $this->allExclusedContexts = null;
    }


    /**
     * Carga contextos específicos por sus claves.
     * Las operaciones posteriores de "all()" y similares operarán solo sobre estos contextos.
     * @param string|array $contextKeys Una o varias claves de contexto.
     * @return self
     * @throws \RuntimeException Si un contexto no se encuentra.
     */
    public function loadContexts(string|array $contextKeys): self
    {
        $keysToLoad = is_array($contextKeys) ? $contextKeys : [$contextKeys];
       // dd($this->contexts);
        foreach ($keysToLoad as $key) {
            if (!isset($this->contexts[$key])) {
                throw new \RuntimeException("El contexto con la clave '$key' no fue encontrado.");
            }
        }
        $this->activeContextKeys = $keysToLoad;
        $this->rebuildEntityMap();
        return $this;
    }

    /**
     * Restaura los contextos activos a todos los disponibles.
     * @return self
     */
    public function resetContexts(): self
    {
        $this->activeContextKeys = array_keys($this->contexts);
        $this->rebuildEntityMap();
        return $this;
    }

    /**
     * Obtiene las claves de todos los contextos disponibles.
     * @return array
     */
    public function getContextKeys(): array
    {
        return array_keys($this->contexts);
    }

    /**
     * Obtiene las claves de los contextos actualmente activos (cargados).
     * @return array
     */
    public function getActiveContextKeys(): array
    {
        return $this->activeContextKeys;
    }

    // ALL ENTITIES

    /**
     * Save permanently the entity, optionally setting a parent.
     *
     * @param FpEntityInterface $new
     * @param string|FpEntityInterface|null $parent
     * @return FpEntityInterface
     */
    public function save(FpEntityInterface $new, string|FpEntityInterface|null $parent = null): FpEntityInterface
    {
        // Si el valor es null, se interpreta como ruta raíz (sin padre)
        if ($parent instanceof FpEntityInterface)
            $parentId = $parent->getId();
        else
            $parentId = $parent;

        $new->setParentId($parentId);

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
        // Actualizar el índice de rutas
        $this->entityMap[$new->getId()] = $context;
        $this->rebuildEntityMap(); // Reconstruye los cachés globales después de una modificación
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
            $this->rebuildEntityMap(); // Reconstruye los cachés globales después de una modificación
        } else {
            throw new \RuntimeException("No se encontró un contexto que contenga la entidad: $entityId");
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
        else
            $id = $entity; // Asegurar que $id esté definido para el caso de string

        // Verifica si la ruta existe en el índice de rutas
        if (isset($this->allFlattenedWhitChilds[$id])) {
            $parentId = $this->allFlattenedWhitChilds[$id]->getParentId();
            if ($parentId !== null) {
                $parentEntity = $this->allFlattenedWhitChilds[$parentId];
                if ($parentEntity) {
                    return collect($parentEntity->getChildrens());
                }
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
     * @param string|FpEntityInterface $entity
     * @return bool
     */
    public function isChild(string|FpEntityInterface $entity): bool
    {
        if ($entity instanceof FpEntityInterface)
            $id = $entity->getId();
        else
            $id = $entity; // Asegurar que $id esté definido para el caso de string

        // Verifica si la ruta existe en el índice de rutas
        return isset($this->entityMap[$id]) && $this->entityMap[$id]->getParentId() !== null;
    }


    /**
     * returns the parent entity of the current entity.
     *
     * @param string|FpEntityInterface $entity
     * @return FpEntityInterface
     * @throws \RuntimeException Si no se encuentra un contexto que contenga la entidad.
     */
    public function parent(string|FpEntityInterface $entity): FpEntityInterface
    {
        if ($entity instanceof FpEntityInterface)
            $id = $entity->getId();
        else
            $id = $entity; // Asegurar que $id esté definido para el caso de string


        // Verifica si la ruta existe en el índice de rutas
        if (isset($this->entityMap[$id])) {
            $context = $this->entityMap[$id];
            $foundEntity = $context->findRoute($id);
            if ($foundEntity && $foundEntity->getParentId() !== null) {
                return $context->findRoute($foundEntity->getParentId());
            }
        }

        throw new \RuntimeException("No se encontró un contexto que contenga la ruta: $id o la ruta no tiene un padre.");
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
        $this->rebuildEntityMap(); // Reconstruye los cachés globales después de una reescritura
        return $this;
    }

    // Métodos para FpBaseEntity para interactuar con el Orchestrator
    public function getParent(FpEntityInterface $entity): ?FpEntityInterface
    {
        if ($entity->getParentId()) {
            return $this->findById($entity->getParentId());
        }
        return null;
    }

    public function moveTo(FpEntityInterface $entity, string|FpEntityInterface $parent): FpEntityInterface
    {
        // Primero, elimina la entidad de su contexto actual
        $this->delete($entity);

        // Luego, guarda la entidad con el nuevo padre
        return $this->save($entity, $parent);
    }
}
