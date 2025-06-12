<?php

namespace Fp\FullRoute\Entities;

use Fp\FullRoute\Contracts\FpEntityInterface;
use Fp\FullRoute\Contracts\OrchestratorInterface;
use Fp\FullRoute\Traits\HasDynamicAccessors;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Abstract base class for all Fp entities, providing common properties,
 * CRUD operations, and a query builder pattern for filtering entities.
 */
abstract class FpBaseEntity implements FpEntityInterface
{
    use HasDynamicAccessors;

    /**
     * @var array Cache de las instancias del orquestador por clase derivada.
     * Esto implementa el patrón Singleton a nivel de la clase derivada.
     * Key: FQCN de la clase derivada (ej. FpNavigation::class)
     * Value: OrchestratorInterface
     */
    protected static array $orchestratorInstances = [];

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

    /**
     * The creation method used for this entity ('make', 'makeGroup', etc.).
     *
     * @var string
     */
    public string $makerMethod = 'make';

    /**
     * Level of the entity in the hierarchy (0-indexed).
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

    /**
     * The access permission required for this entity.
     *
     * @var string|null
     */
    public ?string $accessPermission = null;

    // Additional properties (e.g., for navigation, can be extended with HasDynamicAccessors)
    public ?string $url = null;
    public ?string $urlName = null;
    public ?string $name = null;
    public ?string $description = null;
    public ?string $contextKey = null;

    public bool $isGroup = false;
    public bool $isActive = false;

    /**
     * Constructor for FpBaseEntity.
     *
     * @param string $id The unique identifier for the entity.
     * @param string|null $makerMethod The method used to create this entity.
     */
    public function __construct(string $id, ?string $makerMethod = "make")
    {
        $this->id = $id;
        $this->makerMethod = $makerMethod;
        $this->childrens = new Collection();
    }

    // --- Orchestrator Configuration ---

    /**
     * Get the orchestrator instance specific to this entity type.
     * Each concrete subclass (e.g., FpNavigation, FpRoute) must implement this
     * to specify which Orchestrator it should use.
     *
     * @return OrchestratorInterface
     */
    abstract public static function getOrchestrator(): OrchestratorInterface;

    /**
     * Returns the singleton instance of the Orchestrator for the specific derived class.
     * If an instance doesn't exist, it creates one using `getOrchestrator()` and
     * immediately calls `newQuery()` on it to ensure a fresh state for filtering.
     *
     * @return OrchestratorInterface
     */
    protected static function getOrchestratorSingleton(): OrchestratorInterface
    {
        $class = static::class;
        if (!isset(static::$orchestratorInstances[$class])) {
            // Si no hay una instancia, creamos una y la preparamos para una nueva query.
            // Esto asegura que la primera llamada siempre inicie con un orquestador limpio.
            static::$orchestratorInstances[$class] = static::getOrchestrator()->newQuery();
        }
        return static::$orchestratorInstances[$class];
    }

    /**
     * Reinicia la instancia del orquestador para la clase actual.
     * Esto es útil para forzar un nuevo estado de filtro para una nueva cadena de consulta.
     *
     * @return OrchestratorInterface Una nueva instancia de orquestador limpia.
     */
    protected static function resetOrchestratorSingleton(): OrchestratorInterface
    {
        $class = static::class;
        // Crea una nueva instancia limpia y la almacena.
        static::$orchestratorInstances[$class] = static::getOrchestrator()->newQuery();
        return static::$orchestratorInstances[$class];
    }

    // --- Entity-Specific CRUD and Relation Methods (Delegate to Orchestrator) ---

    // ... (Todos los setters y getters como setId, setParentId, getId, getParentId, etc.,
    // y los métodos de relación como addChild, setChildrens, getChildrens, etc.,
    // y los métodos de propiedades dinámicas como setUrl, getUrl, etc.,
    // permanecen EXACTAMENTE IGUALES a como los tenías.)

    // Solo voy a incluir los métodos que necesitan el orquestador para CRUD y Query Builder,
    // asumiendo que el resto de los métodos están correctos en tu archivo.

    /**
     * Set the ID of the entity.
     *
     * @param string $id
     * @return static
     */
    public function setId(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Set the parent ID of the entity.
     *
     * @param string|null $parentId
     * @return static
     */
    public function setParentId(?string $parentId): static
    {
        $this->parentId = $parentId;
        return $this;
    }

    /**
     * Set the parent entity.
     *
     * @param FpEntityInterface $parent
     * @return static
     */
    public function setParent(FpEntityInterface $parent): static
    {
        $this->parentId = $parent->getId();
        return $this;
    }

    /**
     * Get the ID of the entity.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the parent ID of the entity.
     *
     * @return string|null
     */
    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    /**
     * Get the maker method used to create this entity.
     *
     * @return string
     */
    public function getMakerMethod(): string
    {
        return $this->makerMethod;
    }

    /**
     * Get the instance route ID if this entity is a 'makeSelf' type.
     *
     * @return string|null
     */
    public function getInstanceRouteId(): ?string
    {
        return null;
    }

    /**
     * Set the level of the entity.
     * @param int $level
     * @return static
     */
    public function setLevel(int $level): static
    {
        $this->level = $level;
        return $this;
    }

    /**
     * Get the level of the entity.
     * @return int
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * Add a child entity to this entity.
     * @param FpEntityInterface $child
     * @return static
     */
    public function addChild(FpEntityInterface $child): static
    {
        $child->setParentId($this->id);
        $this->childrens->put($child->getId(), $child);
        return $this;
    }

    /**
     * Set the children collection for this entity.
     * @param Collection $childrens
     * @return static
     */
    public function setChildrens(Collection|array $childrens): static
    {
        if (!($childrens instanceof Collection)) {
            $childrens = new Collection($childrens);
        }

        $this->childrens = $childrens;
        return $this;
    }

    /**
     * Get the children collection of this entity.
     * @return Collection
     */
    public function getChildrens(): Collection
    {
        return $this->childrens;
    }

    /**
     * Set the URL for the entity.
     * @param string|null $url
     * @return static
     */
    public function setUrl(?string $url): static
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Get the URL of the entity.
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Set the URL name for the entity.
     * @param string|null $urlName
     * @return static
     */
    public function setUrlName(?string $urlName): static
    {
        $this->urlName = $urlName;
        return $this;
    }

    /**
     * Get the URL name of the entity.
     * @return string|null
     */
    public function getUrlName(): ?string
    {
        return $this->urlName;
    }

    /**
     * Set the display name for the entity.
     * @param string|null $name
     * @return static
     */
    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the display name of the entity.
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set the description for the entity.
     * @param string|null $description
     * @return static
     */
    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get the description of the entity.
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set the access permission for the entity.
     * @param string|null $permission
     * @return static
     */
    public function setAccessPermission(?string $permission): static
    {
        $this->accessPermission = $permission;
        return $this;
    }

    /**
     * Get the access permission of the entity.
     * @return string|null
     */
    public function getAccessPermission(): ?string
    {
        return $this->accessPermission;
    }


    /**
     * Check if the entity is active.
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Set whether the entity is active.
     * @param bool $isActive
     * @return static
     */
    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    /**
     * Set the context key for the entity.
     * @param string|null $contextKey
     * @return static
     */
    public function setContextKey(?string $contextKey): static
    {
        $this->contextKey = $contextKey;
        return $this;
    }

    /**
     * Get the context key of the entity.
     * @return string|null
     */
    public function getContextKey(): ?string
    {
        return $this->contextKey;
    }


    /**
     * Save the entity to the orchestrator.
     *
     * @param string|FpEntityInterface|null $parent
     * @return static
     */
    public function save(string|FpEntityInterface|null $parent = null): static
    {
        static::getOrchestratorSingleton()->save($this, $parent); // Usar el singleton
        return $this;
    }

    /**
     * Get the siblings (brothers) of the current entity.
     *
     * @return Collection
     */
    public function getBrothers(): Collection
    {
        return static::getOrchestratorSingleton()->getBrothers($this); // Usar el singleton
    }

    /**
     * Find an entity by its ID, including its children.
     *
     * @param string $id
     * @return FpEntityInterface|null
     */
    public static function findByIdWithChilds(string $id): ?FpEntityInterface
    {
        $entity = static::getOrchestratorSingleton()->findByIdWithChilds($id); // Usar el singleton
        return $entity instanceof FpEntityInterface ? $entity : null;
    }

    /**
     * Delete the current entity from the orchestrator.
     *
     * @return bool
     */
    public function delete(): bool
    {
        return static::getOrchestratorSingleton()->delete($this); // Usar el singleton
    }

    /**
     * Find an entity by its ID.
     *
     * @param string $id
     * @return FpEntityInterface|null
     */
    public static function findById(string $id): ?FpEntityInterface
    {
        $entity = static::getOrchestratorSingleton()->findById($id); // Usar el singleton
        return $entity instanceof FpEntityInterface ? $entity : null;
    }

    /**
     * Check if an entity exists in the orchestrator.
     *
     * @param string $id The ID of the entity to check.
     * @return bool
     */
    public static function exists(string $id): bool
    {
        return static::getOrchestratorSingleton()->exists($id); // Usar el singleton
    }

    /**
     * Check if the current entity is a child of another given entity.
     *
     * @param string|FpEntityInterface $entity The potential parent entity.
     * @return bool
     */
    public function isChild(string|FpEntityInterface $entity): bool
    {
        return static::getOrchestratorSingleton()->isChild($this, $entity); // Usar el singleton
    }

    /**
     * Get the parent entity of the current entity.
     *
     * @return FpEntityInterface|null
     */
    public function getParent(): ?FpEntityInterface
    {
        return static::getOrchestratorSingleton()->getParent($this); // Usar el singleton
    }

    /**
     * Move the current entity under a new parent.
     *
     * @param string|FpEntityInterface $parent The ID or instance of the new parent entity.
     * @return static
     */
    public function moveTo(string|FpEntityInterface $parent): static
    {
        static::getOrchestratorSingleton()->moveTo($this, $parent); // Usar el singleton
        return $this;
    }

    // --- Query Builder (Filter Chain) Methods ---

    /**
     * Starts a new "query" to the orchestrator, ensuring temporary filters are cleared.
     * Provides a clear entry point for starting filter chains.
     *
     * @return static A new temporary entity instance for chaining methods.
     */
    public static function newQuery(): static
    {
        // Al iniciar una nueva query, siempre reiniciamos la instancia del orquestador
        // para asegurar un estado limpio y que los filtros se concatenen desde cero.
        static::resetOrchestratorSingleton();
        return new static('temp_id_for_query_builder'); // Devuelve una instancia temporal para encadenar
    }

    /**
     * Configures the orchestrator to load and activate specific contexts.
     *
     * @param string|array $contextKeys One or more context keys to load and activate.
     * @return static A new temporary entity instance for chaining methods.
     */
    public static function loadContexts(string|array $contextKeys): static
    {
        static::getOrchestratorSingleton()->loadContexts($contextKeys); // Usar el singleton
        return new static('temp_id_for_query_builder');
    }

    /**
     * Configures the orchestrator to load and activate all available contexts.
     *
     * @return static A new temporary entity instance for chaining methods.
     */
    public static function loadAllContexts(): static
    {
        static::getOrchestratorSingleton()->loadAllContexts(); // Usar el singleton
        return new static('temp_id_for_query_builder');
    }

    /**
     * Configures the orchestrator to reset active contexts to their default state (usually all available).
     *
     * @return static A new temporary entity instance for chaining methods.
     */
    public static function resetContexts(): static
    {
        static::getOrchestratorSingleton()->resetContexts(); // Usar el singleton
        return new static('temp_id_for_query_builder');
    }

    /**
     * Configures the orchestrator to exclude specific contexts in the next data retrieval operation.
     *
     * @param string|array $contextKeys One or more context keys to exclude.
     * @return static A new temporary entity instance for chaining methods.
     */
    public static function excludeContexts(string|array $contextKeys): static
    {
        static::getOrchestratorSingleton()->excludeContexts($contextKeys); // Usar el singleton
        return new static('temp_id_for_query_builder');
    }

    /**
     * Configures the orchestrator to filter by a maximum depth level.
     *
     * @param int|null $level The maximum depth level. Null for no depth filter.
     * @return static A new temporary entity instance for chaining methods.
     */
    public static function withDepth(?int $level = null): static
    {
        static::getOrchestratorSingleton()->withDepth($level); // Usar el singleton
        return new static('temp_id_for_query_builder');
    }

    /**
     * Configures the orchestrator to filter routes for a specific user ID.
     *
     * @param string|null $userId The user ID to filter by. Null for no user filter.
     * @return static A new temporary entity instance for chaining methods.
     */
    public static function forUser(?string $userId): static
    {
        static::getOrchestratorSingleton()->forUser($userId); // Usar el singleton
        return new static('temp_id_for_query_builder');
    }

    /**
     * Prepares the orchestrator to filter for the current authenticated user.
     * This is a convenience method combining `forUser()` with `auth()->user()->id`.
     *
     * @param string|null $userId Optional: If provided, use this user ID instead of the authenticated one.
     * @return static A new temporary entity instance for chaining methods.
     * @throws RuntimeException If no authenticated user is found and no userId is provided.
     */
    public static function prepareForUser(?string $userId = null): static
    {
        static::getOrchestratorSingleton()->prepareForUser($userId); // Usar el singleton
        return new static('temp_id_for_query_builder');
    }

    /**
     * Resets all filters configured on the current orchestrator instance.
     *
     * @return static A new temporary entity instance for chaining methods.
     */
    public static function resetFilters(): static
    {
        static::getOrchestratorSingleton()->resetFilters(); // Usar el singleton
        return new static('temp_id_for_query_builder');
    }

    // --- Terminal Methods (Data Retrieval) ---

    /**
     * Gets the tree of entities applying all configured filters.
     * This is the final method in a filter chain.
     *
     * @return Collection The filtered tree of entities.
     */
    public static function get(): Collection
    {
        // Los métodos terminales simplemente usan la instancia actual del orquestador.
        return static::getOrchestratorSingleton()->get();
    }

    /**
     * Alias for `get()`. Gets the tree of entities applying all configured filters.
     *
     * @return Collection The filtered tree of entities.
     */
    public static function all(): Collection
    {
        return static::getOrchestratorSingleton()->all();
    }

    /**
     * Gets all entities in a flattened structure, applying configured context filters.
     *
     * @return Collection The flattened collection of entities.
     */
    public static function allFlattened(): Collection
    {
        return static::getOrchestratorSingleton()->allFlattened();
    }

    /**
     * Gets a sub-branch of entities starting from a specific root ID,
     * applying all configured filters to the resulting sub-branch.
     *
     * @param string $rootEntityId The ID of the entity to be the root of the sub-branch.
     * @return Collection The sub-branch of entities.
     */
    public static function getSubBranch(string $rootEntityId): Collection
    {
        return static::getOrchestratorSingleton()->getSubBranch($rootEntityId);
    }

    /**
     * Gets the tree of entities filtered for the current authenticated user.
     * This is a convenience method that combines `prepareForUser()` and `get()`.
     *
     * @return Collection The filtered tree of entities for the current user.
     * @throws RuntimeException If no authenticated user is found.
     */
    public static function getForCurrentUser(): Collection
    {
        return static::getOrchestratorSingleton()->getForCurrentUser();
    }


    // --- Unified Breadcrumbs and Active Branch Logic ---

    /**
     * Gets the breadcrumbs for a specific entity from the tree resulting from the current query chain.
     *
     * @param FpEntityInterface $entity The entity for which to get breadcrumbs.
     * @return Collection A collection of entities representing the breadcrumbs.
     */
    public static function getBreadcrumbs(FpEntityInterface $entity): Collection
    {
        return static::getOrchestratorSingleton()->getBreadcrumbs($entity);
    }

    /**
     * Gets the active branch (the current entity and its active ancestors/descendants)
     * from the tree resulting from the current query chain.
     *
     * @param string|null $activeRouteName The name of the active route. If null, attempts to get from Laravel's request.
     * @return FpEntityInterface|null The root entity of the active branch, or null if not found.
     */
    public static function getActiveBranch(?string $activeRouteName = null): ?FpEntityInterface
    {
        return static::getOrchestratorSingleton()->getActiveBranch($activeRouteName);
    }


    // --- Utility and Orchestrator Management Methods ---

    /**
     * Gets the keys of all available contexts from the orchestrator.
     * @return array
     */
    public static function getContextKeys(): array
    {
        // Para obtener las claves de contexto generales, no necesitamos una instancia "limpia"
        // del orquestador, ya que es información global. Podríamos usar getOrchestrator()
        // que debería devolver una instancia "base" que conoce todos los contextos.
        // Pero para asegurar que se use la instancia que ya conoce todos los contextos,
        // podemos simplemente usar el singleton sin resetearlo.
        return static::getOrchestratorSingleton()->getContextKeys();
    }

    /**
     * Gets the keys of the contexts currently active in the orchestrator.
     * @return array
     */
    public static function getActiveContextKeys(): array
    {
        return static::getOrchestratorSingleton()->getActiveContextKeys();
    }

    /**
     * Recreates and rewrites all contexts in the orchestrator. Useful for invalidating caches.
     * @return void
     */
    public static function rewriteAllContext(): void
    {
        static::getOrchestratorSingleton()->rewriteAllContext();
    }

    /**
     * Returns the attributes that should be omitted when the entity is serialized (e.g., to JSON).
     * Override this in child classes.
     * @return array
     */
    public function getOmmittedAttributes(): array
    {
        return [];
    }
}
