<?php

namespace Fp\FullRoute\Entities;


use Fp\FullRoute\Contracts\FpEntityInterface;
use Fp\FullRoute\Contracts\OrchestratorInterface;
use Fp\FullRoute\Traits\HasDynamicAccessors;

use Illuminate\Support\Collection;


abstract class FpBaseEntity implements FpEntityInterface
{
    use HasDynamicAccessors;

    /**
     * The ID of the parent entity, if any.
     *
     * @var string|null
     */
    public ?string $parentId = null;

    /**
     * The unique identifier for the entity.
     *
     * @var string
     */
    public string $id;

    public string $makerMethod = 'make';

    /**
     * Level of the entity in the hierarchy.
     *
     * @var int
     */
    public int $level = 0;

    /**
     * The children of the entity.
     *
     * @var Collection
     */
    protected Collection|array $childrens;

    public ?string $accessPermission = null; // Corregido el nombre de la propiedad

    public function __construct(string $id, ?string $makerMethod = "make")
    {
        $this->id = $id;
        $this->makerMethod = $makerMethod;
        $this->childrens = new Collection(); // Inicializar como colección
    }

    // CRUD Methods
    /**
     * Create a new entity instance.
     *
     * @param string $id
     * @return FpEntityInterface
     */
    public static function make(string $id): FpEntityInterface
    {
        return new static($id, "make");
    }


    public static function makeGroup(string $id): FpEntityInterface
    {
        return new static($id, "makeGroup");
    }



    /**
     * Get the orchestrator instance for the entity.
     *
     * @return OrchestratorInterface
     */
    abstract public static function getOrchestrator(): OrchestratorInterface;


    abstract public function getOmmittedAttributes(): array;


    public function setId(string $id): static
    {
        $this->id = $id;
        return $this;
    }
    /**
     * Set the parent ID for the entity.
     *
     * @param string|null $parentId
     * @return static
     */
    public function setParentId(?string $parentId): static
    {
        $this->parentId = $parentId;
        return $this;
    }

    public function setParent(FpEntityInterface $parent): static
    {
        $this->parentId = $parent->getId();
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function getMakerMethod(): string
    {
        return $this->makerMethod;
    }

    public function getInstanceRouteId(): ?string
    {
        // Si necesitas una propiedad específica para esto, debes definirla en FpBaseEntity
        // y asegurarte de que se establezca al crear con 'makeSelf'.
        // Por ahora, si no existe un método claro para obtenerla, asumimos que puede ser nulo o necesitará una implementación específica.
        return null; // O implementar lógica para obtener el ID de la ruta de la instancia
    }


    /**
     * Save permanently the entity, optionally setting a parent.
     *
     * @param string|FpEntityInterface|null $parent
     * @return static
     */
    public function save(string|FpEntityInterface|null $parent = null): static
    {
        static::getOrchestrator()
            ->save($this, $parent);

        return $this;
    }

    public function getBrothers(): Collection
    {
        return static::getOrchestrator()
            ->getBrothers($this);
    }

    public static function findByIdWithChilds(string $id): ?FpEntityInterface
    {
        $entity = static::getOrchestrator()
            ->findByIdWithChilds($id);

        return $entity instanceof FpEntityInterface ? $entity : null;
    }

    /**
     * Delete the entity permanently.
     *
     *
     * @return bool
     */
    public function delete(): bool
    {
        return static::getOrchestrator()
            ->delete($this);
    }

    /**
     * Find an entity by its ID.
     *
     * @param string $id
     * @return FpEntityInterface|null
     */
    public static function findById(string $id): ?FpEntityInterface
    {
        $entity = static::getOrchestrator()
            ->findById($id);
        return $entity instanceof FpEntityInterface ? $entity : null;
    }

    /**
     * Find an entity by its ID, or throw an exception if not found.
     *
     * @param string $id
     * @return FpEntityInterface
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findByParamName(string $paramName, string $value): ?Collection
    {
        // Este método no estaba implementado en el Orchestrator que me diste.
        // Si lo necesitas, deberás agregarlo en BaseOrchestrator.
        // Por ahora, lanzaré un error o devolveré null.
        // throw new \BadMethodCallException("findByParamName no está implementado en el Orchestrator.");
        return null;
    }

    /**
     * get all entities.
     * @return Collection
     */
    public static function all(): Collection
    {
        return static::getOrchestrator()
            ->all();
    }

    /**
     * Get all entities grouped by file.
     *
     * @return Collection
     */
    public static function getAllsByFile(string $filePath): Collection
    {
        // Este método no estaba implementado en el Orchestrator que me diste.
        // Si lo necesitas, deberás agregarlo en BaseOrchestrator.
        // throw new \BadMethodCallException("getAllsByFile no está implementado en el Orchestrator.");
        return collect();
    }


    /**
     * Get all entities grouped by file, flattened.
     *
     * @return Collection
     */
    public static function getAllsByFileFlattened(): Collection
    {
        // Este método no estaba implementado en el Orchestrator que me diste.
        // Si lo necesitas, deberás agregarlo en BaseOrchestrator.
        // throw new \BadMethodCallException("getAllsByFileFlattened no está implementado en el Orchestrator.");
        return collect();
    }


    /**
     * Get all entities in a flattened structure.
     *
     * @return Collection
     */
    public static function allFlattened(): Collection
    {
        return static::getOrchestrator()
            ->allFlattened();
    }

    /**
     * Get all entities in a tree structure.
     *
     * @return Collection
     */
    public static function allInTree(): Collection
    {
        // Asumiendo que 'all()' ya devuelve el árbol
        return static::getOrchestrator()
            ->all();
    }


    // Utility Methods

    /**
     * Check if an entity exists by its ID.
     *
     * @param string $id
     * @return bool
     */
    public static function exists(string $id): bool
    {
        return static::getOrchestrator()
            ->exists($id);
    }



    /**
     * Check if an entity is a child of another entity.
     *
     * @param string|FpEntityInterface $entity
     * @return bool
     */
    public function isChild(string|FpEntityInterface $entity): bool
    {
        return static::getOrchestrator()
            ->isChild($entity);
    }

    /**
     * returns the parent entity of the current entity.
     *
     * @param string|FpEntityInterface $entity
     * @return FpEntityInterface
     */
    public function parent(string|FpEntityInterface $entity): FpEntityInterface
    {
        return static::getOrchestrator()
            ->parent($entity);
    }

    /**
     * Get the breadcrumbs for the entity. in the tree structure.
     *
     * @return Collection
     */
    public function getBreadcrumbs(): Collection
    {
        return static::getOrchestrator()
            ->getBreadcrumbs($this);
    }

    /**
     * Move the entity to a new parent.
     *
     * @param string|FpEntityInterface $parent
     * @return static
     */
    public function moveTo(string|FpEntityInterface $parent): static
    {
        return static::getOrchestrator()
            ->moveTo($this, $parent);
    }


    /**
     * Get the ID of the entity.
     *
     * @return string
     */
    public function getParent(): ?FpEntityInterface
    {
        return static::getOrchestrator()
            ->getParent($this);
    }

    /**
     * add a child to the current entity.
     *
     * @param FpEntityInterface $child
     * @return static
     */
    public function addChild(FpEntityInterface $child): static
    {
        $child->setParentId($this->getId());
        $child->setLevel($this->getLevel() + 1);
        $this->childrens->push($child);
        return $this;
    }

    /**
     * Set the children for the entity.
     * @param array|Collection $children
     * @return static
     */
    public function setChildrens(array|Collection $children): static
    {
        $this->childrens = collect($children);
        return $this;
    }

    /**
     * Get the children of the entity.
     * @return Collection
     */
    public function getChildrens(): Collection
    {
        return $this->childrens;
    }

    public function setLevel(int $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getLevel(): int
    {
        return $this->level;
    }


    // Context Methods (delegando al Orchestrator)

    /**
     * Obtiene las claves de todos los contextos disponibles en el orquestador.
     * @return array
     */
    public static function getContextKeys(): array
    {
        return static::getOrchestrator()->getContextKeys();
    }

    /**
     * Obtiene las claves de los contextos actualmente activos en el orquestador.
     * @return array
     */
    public static function getActiveContextKeys(): array
    {
        return static::getOrchestrator()->getActiveContextKeys();
    }

    /**
     * Carga contextos específicos en el orquestador.
     * @param string|array $contextKeys
     * @return OrchestratorInterface
     */
    public static function loadContexts(string|array $contextKeys): OrchestratorInterface
    {
        return static::getOrchestrator()->loadContexts($contextKeys);
    }

    /**
     * Restaura los contextos activos a todos los disponibles en el orquestador.
     * @return OrchestratorInterface
     */
    public static function resetContexts(): OrchestratorInterface
    {
        return static::getOrchestrator()->resetContexts();
    }

    /**
     * Establece los contextos a excluir en el orquestador.
     * @param string|array $contextKeys
     * @return OrchestratorInterface
     */
    public static function excludeContexts(string|array $contextKeys): OrchestratorInterface
    {
        return static::getOrchestrator()->excludeContexts($contextKeys);
    }

    /**
     * Obtiene todas las entidades, excluyendo los contextos configurados en el orquestador.
     * @return Collection
     */
    public static function allExcludingContexts(): Collection
    {
        return static::getOrchestrator()->allExcludingContexts();
    }

    /**
     * Obtiene solo las entidades de los contextos configurados como excluidos en el orquestador.
     * @return Collection
     */
    public static function allExclusedContexts(): Collection
    {
        return static::getOrchestrator()->allExclusedContexts();
    }

    /**
     * Obtiene una sub-rama de entidades a partir de un ID de entidad raíz.
     * @param string $rootEntityId
     * @return Collection
     */
    public static function getSubBranch(string $rootEntityId): Collection
    {
        return static::getOrchestrator()->getSubBranch($rootEntityId);
    }

    /**
     * Retorna el árbol de entidades tal que el usuario actual tenga permiso de acceso o que sean públicas.
     * @return Collection
     */
    public static function getAllOfCurrenUser(): Collection
    {
        return static::getOrchestrator()->getAllOfCurrenUser();
    }

    /**
     * Retorna el árbol de entidades filtrado por un conjunto de permisos dados.
     * @param array|Collection $permissions
     * @return Collection
     */
    public static function getFilteredWithPermissions(array|Collection $permissions): Collection
    {
        return static::getOrchestrator()->getFilteredWithPermissions($permissions);
    }

    public static function rewriteAllContext(): void
    {
        static::getOrchestrator()
            ->rewriteAllContext();
    }
}