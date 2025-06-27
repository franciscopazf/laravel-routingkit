<?php

namespace FP\RoutingKit\Entities;

use FP\RoutingKit\Contracts\FPEntityInterface;
use FP\RoutingKit\Contracts\FPIsOrchestrableInterface;
use FP\RoutingKit\Contracts\FPOrchestratorInterface;
use FP\RoutingKit\Features\DataOrchestratorFeature\FPBaseOrchestrator;
use FP\RoutingKit\Features\InteractiveFeature\FPTreeNavigator;
use FP\RoutingKit\Traits\HasDynamicAccessors;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Abstract base class for all FP entities, providing common properties,
 * CRUD operations, and a query builder pattern for filtering entities.
 */
abstract class FPBaseEntity implements FPEntityInterface, FPIsOrchestrableInterface
{
    use HasDynamicAccessors;


    /**
     * @var array Cache de las instancias del orquestador por clase derivada (Singleton por Orchestrator).
     * Key: FQCN de la clase derivada (ej. FPNavigation::class)
     * Value: FPOrchestratorInterface
     */
    protected static array $orchestratorInstances = [];

    /**
     * @var array Cache de las instancias TEMPORALES de la entidad para el Query Builder (Singleton por Entidad de Query Builder).
     * Key: FQCN de la clase derivada (ej. FPNavigation::class)
     * Value: FPBaseEntity (o su clase derivada)
     */
    protected static array $queryBuilderInstances = [];

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


    // ... constructor, setters, getters existentes ...

    /**
     * The creation method used for this entity ('make', 'makeGroup', etc.).
     *
     * @var string
     */
    public ?string $makerMethod = 'make';

    /**
     * Level of the entity in the hierarchy (0-indexed).
     *
     * @var int
     */
    public int $level = 0;

    /**
     * The child items of the entity.
     *
     * @var Collection
     */
    protected Collection|array $items;

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
     * Constructor for FPBaseEntity.
     *
     * @param string $id The unique identifier for the entity.
     * @param string|null $makerMethod The method used to create this entity.
     */
    public function __construct(string $id, ?string $makerMethod = "make")
    {
        $this->id = $id;
        $this->makerMethod = $makerMethod;
        $this->items = new Collection();
    }

    // --- Orchestrator Configuration ---

    /**
     * Get the orchestrator instance specific to this entity type.
     * Each concrete subclass (e.g., FPNavigation, FPRoute) must implement this
     * to specify which Orchestrator it should use.
     *
     * @return FPOrchestratorInterface
     */
    public static function getOrchestrator(): FPOrchestratorInterface
    {
        // Se asegura que se obtiene una instancia base del Orchestrator.
        // La lógica de newQuery() se manejará en getOrchestratorSingleton().
        return FPBaseOrchestrator::make(static::getOrchestratorConfig());
    }

    /**
     * Returns the configuration for the orchestrator.
     * This method should be implemented by each concrete entity class to
     * provide the specific configuration needed for the orchestrator.
     *
     * @return array The configuration array for the orchestrator.
     */
    abstract public static function getOrchestratorConfig(): array;

    /**
     * Returns the singleton instance of the Orchestrator for the specific derived class.
     * If an instance doesn't exist, it creates one using `getOrchestrator()`.
     * **Importante:** Este método NO llama a `newQuery()`. `newQuery()` se maneja explícitamente
     * cuando se inicia una nueva cadena de consulta desde la entidad.
     *
     * @return FPOrchestratorInterface
     */
    protected static function getOrchestratorSingleton(): FPOrchestratorInterface
    {
        $class = static::class;
        if (!isset(static::$orchestratorInstances[$class])) {
            static::$orchestratorInstances[$class] = static::getOrchestrator();
        }
        return static::$orchestratorInstances[$class];
    }

    /**
     * Reinicia la instancia del orquestador para la clase actual.
     * Esto es útil para forzar un nuevo estado de filtro para una nueva cadena de consulta.
     * Llama a `newQuery()` en el orquestador para resetear sus filtros y caché.
     *
     * @return FPOrchestratorInterface Una instancia de orquestador limpia.
     */
    protected static function resetOrchestratorSingleton(): FPOrchestratorInterface
    {
        $class = static::class;
        // Obtenemos la instancia singleton y luego llamamos a newQuery() en ella.
        static::$orchestratorInstances[$class] = static::getOrchestratorSingleton()->newQuery();
        return static::$orchestratorInstances[$class];
    }

    // --- Query Builder Entity Instance Management ---

    /**
     * Obtiene y/o crea la única instancia de la entidad (Query Builder) para la clase derivada actual.
     * Este es el método central para obtener la instancia para encadenar filtros.
     *
     * @param bool $reset Si es true, fuerza la creación de una nueva instancia de Query Builder y reinicia el orquestador asociado.
     * @return static La instancia Singleton de la entidad de Query Builder.
     */
    public static function getInstance(bool $reset = false): static
    {
        $class = static::class;

        if (!isset(static::$queryBuilderInstances[$class]) || $reset) {
            // Si no existe la instancia o si se solicita un reset, la creamos/reiniciamos.
            static::$queryBuilderInstances[$class] = new static('temp_id_for_query_builder', "make");
            // Y si se reinicia la instancia del Query Builder, también reiniciamos el Orchestrator.
            // Esto asegura que cada nueva cadena de query comience con filtros limpios.
            static::resetOrchestratorSingleton();
        }

        return static::$queryBuilderInstances[$class];
    }


    // --- Entity-Specific CRUD and Relation Methods (Delegate to Orchestrator) ---

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
     * @param FPEntityInterface $parent
     * @return static
     */
    public function setParent(FPEntityInterface $parent): static
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
     * Add an item (child entity) to this entity.
     * @param FPEntityInterface $item
     * @return static
     */
    public function addItem(FPEntityInterface $item): static
    {
        $item->setParentId($this->id);
        $item->setLevel($this->level + 1); // Increment level for child items
        $this->items->put($item->getId(), $item);
        return $this;
    }

    /**
     * Set the items (children) collection for this entity.
     * @param Collection $items
     * @return static
     */
    public function setItems(Collection|array $items): static
    {
        if (!($items instanceof Collection)) {
            $items = new Collection($items);
        }

        $this->items = $items;
        return $this;
    }

    /**
     * Get the items (children) collection of this entity.
     * @return Collection
     */
    public function getItems(): Collection
    {
        if (!($this->items instanceof Collection)) {
            $this->items = new Collection($this->items);
        }
        return $this->items;
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
     * Check if the entity is a group.
     * @return bool
     */
    public function isGroup(): bool
    {
        return $this->isGroup;
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
     * @param string|FPEntityInterface|null $parent
     * @return static
     */
    public function save(string|FPEntityInterface|null $parent = null): static
    {
        if ($parent instanceof FPEntityInterface) {
            $this->parentId = $parent->getId();
        } else if (is_string($parent)) {
            $this->parentId = $parent;
        }

        // Al guardar, la operación es directa al orquestador singleton.
        // No afecta el estado de la "query" actual del Query Builder.
        static::getOrchestratorSingleton()->save($this);
        return $this;
    }

    /**
     * Get the siblings (brothers) of the current entity.
     *
     * @return Collection
     */
    public function getBrothers(): Collection
    {
        // Este método recupera hermanos basados en el estado actual del Orchestrator
        // Si no se llamó a newQuery(), usará el último caché.
        return static::getOrchestratorSingleton()->getBrothers($this);
    }

    /**
     * Find an entity by its ID, including its child items.
     * This method leverages the orchestrator's internal flattened cache for fast lookup.
     *
     * @param string $id
     * @return FPEntityInterface|null
     */
    public static function findByIdWithItems(string $id): ?FPEntityInterface
    {
        // Aseguramos que el orquestador ha construido y filtrado el árbol y su caché.
        // La llamada a get() en el orquestador lo fuerza si es necesario.
        static::getOrchestratorSingleton()->get();

        // Accedemos directamente a la caché aplanada del orquestador, que contiene los
        // clones finales de las entidades con sus relaciones de hijos ya establecidas.
        $filteredEntitiesCache = static::getOrchestratorSingleton()->getFilteredEntitiesCache(); // Nuevo método en Orchestrator

        // Si la entidad se encuentra, es el clon final que queremos. Se recomienda clonarla para la salida.
        $entity = $filteredEntitiesCache->get($id);

        // Devolvemos un clon si se encuentra, para evitar modificaciones directas al caché.
        return $entity instanceof FPEntityInterface ? clone $entity : null;
    }


    /**
     * Delete the current entity from the orchestrator.
     *
     * @return bool
     */
    public function delete(): bool
    {
        // Operación directa de eliminación, no afecta el estado de la query.
        return static::getOrchestratorSingleton()->delete($this);
    }

    /**
     * Find an entity by its ID.
     * This method leverages the orchestrator's internal flattened cache for fast lookup.
     *
     * @param string $id
     * @return FPEntityInterface|null
     */
    public static function findById(string $id): ?FPEntityInterface
    {
        // Aseguramos que el orquestador ha construido y filtrado el árbol y su caché.
        static::getOrchestratorSingleton()->get();

        // Accedemos directamente a la caché aplanada del orquestador.
        $filteredEntitiesCache = static::getOrchestratorSingleton()->getFilteredEntitiesCache(); // Nuevo método en Orchestrator

        // Devolvemos un clon si se encuentra, para evitar modificaciones directas al caché.
        $entity = $filteredEntitiesCache->get($id);
        return $entity instanceof FPEntityInterface ?  $entity : null;
    }


    /**
     * Check if an entity exists in the orchestrator.
     * This method leverages the orchestrator's internal flattened cache for fast lookup.
     *
     * @param string $id The ID of the entity to check.
     * @return bool
     */
    public static function exists(string $id): bool
    {
        // Aseguramos que el orquestador ha construido y filtrado el árbol y su caché.
        static::getOrchestratorSingleton()->get();

        // Accedemos directamente a la caché aplanada del orquestador.
        $filteredEntitiesCache = static::getOrchestratorSingleton()->getFilteredEntitiesCache(); // Nuevo método en Orchestrator
        return $filteredEntitiesCache->has($id);
    }

    /**
     * Check if the current entity is a child of another given entity.
     *
     * @param string|FPEntityInterface $entity The potential parent entity.
     * @return bool
     */
    public function isChild(string|FPEntityInterface $entity): bool
    {
        // Este método operará sobre el estado actual del Orchestrator.
        return static::getOrchestratorSingleton()->isChild($this, $entity);
    }

    /**
     * Get the parent entity of the current entity.
     * This method leverages the orchestrator's internal flattened cache for fast lookup.
     *
     * @return FPEntityInterface|null
     */
    public function getParent(): ?FPEntityInterface
    {
        // Este método ahora usa el nuevo getParentNode en el Orchestrator,
        // que aprovecha el caché del árbol ya filtrado.
        return static::getOrchestratorSingleton()
                        ->getFilteredEntitiesCache()
                        ->get($this->parentId) ?? null
    }

    /**
     * Move the current entity under a new parent.
     *
     * @param string|FPEntityInterface $parent The ID or instance of the new parent entity.
     * @return static
     */
    public function moveTo(string|FPEntityInterface $parent): static
    {
        // Operación directa de movimiento, no afecta el estado de la query.
        static::getOrchestratorSingleton()->moveTo($this, $parent);
        return $this;
    }

    // --- Query Builder (Filter Chain) Methods ---

    /**
     * Starts a new "query" to the orchestrator, ensuring temporary filters are cleared.
     * Provides a clear entry point for starting filter chains.
     *
     * @return static The single temporary entity instance for chaining methods.
     */
    public static function newQuery(): static
    {
        // Al iniciar una nueva query, forzamos un reset completo del Query Builder Entity y su Orchestrator.
        return static::getInstance(true);
    }

    /**
     * Configures the orchestrator to load and activate specific contexts.
     *
     * @param string|array $contextKeys One or more context keys to load and activate.
     * @return static The single temporary entity instance for chaining methods.
     */
    public static function loadContexts(string|array $contextKeys): static
    {
        static::getOrchestratorSingleton()->loadContexts($contextKeys);
        return static::getInstance(); // Devuelve la misma instancia para encadenar
    }

    /**
     * Configures the orchestrator to load and activate all available contexts.
     *
     * @return static The single temporary entity instance for chaining methods.
     */
    public static function loadAllContexts(): static
    {
        static::getOrchestratorSingleton()->loadAllContexts();
        return static::getInstance(); // Devuelve la misma instancia para encadenar
    }

    /**
     * Configures the orchestrator to reset active contexts to their default state (usually all available).
     *
     * @return static The single temporary entity instance for chaining methods.
     */
    public static function resetContexts(): static
    {
        static::getOrchestratorSingleton()->resetContexts();
        return static::getInstance(); // Devuelve la misma instancia para encadenar
    }

    /**
     * Configures the orchestrator to exclude specific contexts in the next data retrieval operation.
     *
     * @param string|array $contextKeys One or more context keys to exclude.
     * @return static The single temporary entity instance for chaining methods.
     */
    public static function excludeContexts(string|array $contextKeys): static
    {
        static::getOrchestratorSingleton()->excludeContexts($contextKeys);
        return static::getInstance(); // Devuelve la misma instancia para encadenar
    }

    /**
     * Configures the orchestrator to filter by a maximum depth level.
     *
     * @param int|null $level The maximum depth level. Null for no depth filter.
     * @return static The single temporary entity instance for chaining methods.
     */
    public static function withDepth(?int $level = null): static
    {
        static::getOrchestratorSingleton()->withDepth($level);
        return static::getInstance(); // Devuelve la misma instancia para encadenar
    }

    /**
     * Configures the orchestrator to filter routes for a specific user ID.
     *
     * @param string|null $userId The user ID to filter by. Null for no user filter.
     * @return static The single temporary entity instance for chaining methods.
     */
    public static function forUser(?string $userId): static
    {
        static::getOrchestratorSingleton()->forUser($userId);
        return static::getInstance(); // Devuelve la misma instancia para encadenar
    }

    /**
     * Prepares the orchestrator to filter for the current authenticated user.
     * This is a convenience method combining `forUser()` with `auth()->user()->id`.
     *
     * @param string|null $userId Optional: If provided, use this user ID instead of the authenticated one.
     * @return static The single temporary entity instance for chaining methods.
     * @throws RuntimeException If no authenticated user is found and no userId is provided.
     */
    public static function prepareForUser(?string $userId = null): static
    {
        static::getOrchestratorSingleton()->prepareForUser($userId);
        return static::getInstance(); // Devuelve la misma instancia para encadenar
    }

    /**
     * Resets all filters configured on the current orchestrator instance.
     *
     * @return static The single temporary entity instance for chaining methods.
     */
    public static function resetFilters(): static
    {
        static::getOrchestratorSingleton()->resetFilters();
        return static::getInstance(); // Devuelve la misma instancia para encadenar
    }

    // --- NUEVOS MÉTODOS DE FILTRADO EN EL QUERY BUILDER ---

    /**
     * Filtra para mostrar solo los archivos de un contexto específico (o varios).
     * @param string|array $contextKeys La clave o claves de contexto a incluir.
     * @return static
     */
    public static function filterOnlyFiles(string|array $contextKeys): static
    {
        static::getOrchestratorSingleton()->filterOnlyFiles($contextKeys);
        return static::getInstance();
    }

    /**
     * Filtra para mostrar todas las rutas/archivos de todos los contextos disponibles.
     * @return static
     */
    public static function filterAllFiles(): static
    {
        static::getOrchestratorSingleton()->filterAllFiles();
        return static::getInstance();
    }

    /**
     * Configura el orquestador para filtrar las rutas para el usuario autenticado actual.
     * @return static
     * @throws RuntimeException Si no hay usuario autenticado.
     */
    public static function filterForCurrentUser(): static
    {
        static::getOrchestratorSingleton()->filterForCurrentUser();
        return static::getInstance();
    }

    /**
     * Configura el filtro de profundidad.
     * @param int|null $level El nivel de profundidad.
     * @return static
     */
    public static function filterByDepth(?int $level = null): static
    {
        static::getOrchestratorSingleton()->filterByDepth($level);
        return static::getInstance();
    }

    /**
     * Controla si los nodos grupo sin ítems (hijos) se deben incluir en el resultado final.
     * Por defecto, no se incluyen a menos que se llame a este método con `true`.
     *
     * **Alias para `withEmptyGroups()` en el Orchestrator.**
     *
     * @param bool $value `true` para forzar la inclusión de grupos vacíos, `false` para omitirlos.
     * @return static
     */
    public static function withEmptyGroups(bool $value = true): static
    {
        static::getOrchestratorSingleton()->withEmptyGroups($value);
        return static::getInstance();
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
        // El orquestador se encargará de construir o devolver el caché ya filtrado.
        return static::getOrchestratorSingleton()->get();
    }

    /**
     * Alias for `get()`. Gets the tree of entities applying all configured filters.
     *
     * @return Collection The filtered tree of entities.
     */
    public static function all(): Collection
    {
        // El orquestador se encargará de construir o devolver el caché ya filtrado.
        return static::getOrchestratorSingleton()->all();
    }

    /**
     * Gets all entities in a flattened structure, applying configured context filters.
     *
     * @return Collection The flattened collection of entities.
     */
    public static function allFlattened(): Collection
    {
        // Esto ahora devuelve la colección aplanada de entidades crudas basada en los contextos activos,
        // no el árbol filtrado. Si necesitas el árbol filtrado aplanado, usa `static::get()` y luego `flatten()`.
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
        // El orquestador se encargará de obtener la sub-rama del árbol ya filtrado.
        return static::getOrchestratorSingleton()->getSubBranch($rootEntityId);
    }

    /**
     * Gets multiple sub-branches from the tree, each starting from a specified root ID.
     * All filters configured on the current orchestrator instance will be applied to the
     * overall tree before extracting the sub-branches.
     *
     * @param array $rootEntityIds An array of IDs of entities that will be the roots of the sub-branches.
     * @return Collection A collection where each item is the root entity of a sub-branch,
     * with its children nested, from the filtered tree.
     */
    public static function getSubBranches(array $rootEntityIds): Collection
    {
        // El orquestador se encargará de obtener las sub-ramas del árbol ya filtrado.
        return static::getOrchestratorSingleton()->getSubBranches($rootEntityIds);
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
        // El orquestador se encargará de esto internamente.
        return static::getOrchestratorSingleton()->getForCurrentUser();
    }

    // --- Unified Breadcrumbs and Active Branch Logic ---

    /**
     * Gets the breadcrumbs for a specific entity from the tree resulting from the current query chain.
     *
     * @param FPEntityInterface $entity The entity for which to get breadcrumbs.
     * @return Collection A collection of entities representing the breadcrumbs.
     */
    public static function getBreadcrumbs(FPEntityInterface $entity): Collection
    {
        // El orquestador se encargará de obtener las migas de pan del árbol ya filtrado.
        return static::getOrchestratorSingleton()->getBreadcrumbs($entity);
    }

    /**
     * Gets the active branch (the current entity and its active ancestors/descendants)
     * from the tree resulting from the current query chain.
     *
     * @param string|null $activeRouteName The name of the active route. If null, attempts to get from Laravel's request.
     * @return FPEntityInterface|null The root entity of the active branch, or null if not found.
     */
    public static function getActiveBranch(?string $activeRouteName = null): ?FPEntityInterface
    {
        // El orquestador se encargará de obtener la rama activa del árbol ya filtrado.
        return static::getOrchestratorSingleton()->getActiveBranch($activeRouteName);
    }

    /**
     * **NUEVO:** Obtiene las migas de pan para la ruta activa actual.
     * Aplica los filtros configurados en la cadena de consulta actual.
     *
     * @param string|null $activeRouteName El nombre de la ruta activa. Si es null, se intenta obtener de la solicitud de Laravel.
     * @return Collection Una colección de entidades que representan las migas de pan para la ruta activa.
     */
    public static function getBreadcrumbsForCurrentRoute(?string $activeRouteName = null): Collection
    {
        // El orquestador se encargará de esto.
        return static::getOrchestratorSingleton()->getBreadcrumbsForCurrentRoute($activeRouteName);
    }

    // --- Utility and Orchestrator Management Methods ---

    /**
     * Gets the keys of all available contexts from the orchestrator.
     * @return array
     */
    public static function getContextKeys(): array
    {
        return static::getOrchestratorSingleton()->getContextKeys();
    }

    /**
     * Gets the keys of the contexts currently active in the orchestrator.
     * @return array
     */
    public static function getActiveContextKeys(): array
    {
        return static::getOrchestratorSingleton()->getCurrentIncludedContextKeys();
    }

    /**
     * Recreates and rewrites all contexts in the orchestrator. Useful for invalidating caches.
     * @return void
     */
    public static function rewriteAllContext(): void
    {
        // Esta operación fuerza una relectura completa, así que invalida todos los cachés.
        static::getOrchestratorSingleton()->rewriteAllContext();
        // Además, reseteamos las instancias singleton para asegurar que la próxima "query" comience limpia.
        static::$orchestratorInstances = [];
        static::$queryBuilderInstances = [];
    }


    public static function seleccionar(
        ?string $omitId = '',
        string $label = 'Selecciona una ruta',
        bool $soloGrupos = false,
        bool $permitirSeleccionarRaiz = true
    ): ?string {
        // static::all() ya se encarga de usar el orquestador con los filtros aplicados.
        return FPTreeNavigator::make(static::all(), static::class)
            ->soloGrupos($soloGrupos)
            ->permitirSeleccionarRaiz($permitirSeleccionarRaiz)
            ->omitirId($omitId)
            ->conEtiqueta($label)
            ->navegar();
    }

    /**
     * Obtiene el nodo activo más específico (el "último" nodo activo) del árbol resultante
     * de la cadena de consulta actual.
     *
     * @param string|null $activeRouteName El nombre de la ruta activa. Si es null, se intenta obtener de la solicitud de Laravel.
     * @return FPEntityInterface|null El nodo activo actual, o null si no se encuentra.
     */
    public static function getCurrentActiveNode(?string $activeRouteName = null): ?FPEntityInterface
    {
        // Este método se encargará de obtener el nodo activo del árbol ya filtrado.
        return static::getOrchestratorSingleton()->getCurrentActiveNode($activeRouteName);
    }

     /**
     * Obtiene el nodo padre de una entidad específica por su ID.
     * Aplica todos los filtros configurados en la cadena de consulta actual.
     *
     * @param string $entityId El ID de la entidad (hija) de la cual se quiere obtener el padre.
     * @return FPEntityInterface|null El nodo padre, o null si no se encuentra o no tiene padre en el árbol filtrado.
     */
    public static function getParentOf(string $entityId): ?FPEntityInterface
    {
        // Este método se encargará de obtener el padre del árbol ya filtrado.
        return static::getOrchestratorSingleton()->getParentNode($entityId);
    }

    /**
     * Obtiene el nodo padre de la ruta activa actual, incluyendo todos sus hijos filtrados.
     * Esto te permitirá obtener la "sección principal" a la que pertenece la ruta activa.
     * Aplica todos los filtros configurados en la cadena de consulta actual.
     *
     * @param string|null $activeRouteName El nombre de la ruta activa. Si es null, intenta obtenerlo de Laravel's request.
     * @return FPEntityInterface|null El nodo padre del activo actual con sus hijos, o null si no se encuentra.
     */
    public static function getActiveNodeParentWithChildren(?string $activeRouteName = null): ?FPEntityInterface
    {
        return static::getOrchestratorSingleton()->getActiveNodeParentWithChildren($activeRouteName);
    }


    /**
     * Construye una nueva instancia de la entidad (o una subclase) a partir de un array de atributos.
     * Las claves del array que no corresponden a propiedades definidas se agregarán dinámicamente.
     *
     * @param array $data Los atributos para construir la entidad. Debe incluir 'id'.
     * @return static La instancia de la entidad construida.
     * @throws RuntimeException Si 'id' no está presente o no es una cadena en el array de datos.
     */
    public static function buildFromArray(array $data): static
    {
        if (!isset($data['id']) || !is_string($data['id'])) {
            throw new RuntimeException("Cannot build entity: 'id' must be a string and present in the data array.");
        }

        $instance = new static($data['id']);

        // Asignar los atributos del array a la instancia
        foreach ($data as $key => $value) {
            if ($key === 'id') {
                continue;
            }

            if ((is_string($value) && trim($value) === '') || (is_array($value) && empty($value))) {
                continue;
            }

            if ($key === 'items' && is_array($value)) {
                $instance->{$key} = new Collection($value);
            } else {
                $instance->{$key} = $value;
            }
        }

        return $instance;
    }
}