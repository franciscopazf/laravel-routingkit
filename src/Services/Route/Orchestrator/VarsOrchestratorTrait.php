<?php

namespace Fp\FullRoute\Services\Route\Orchestrator;

use App\Models\User;
use Fp\FullRoute\Contracts\FpEntityInterface;
use Illuminate\Support\Collection;
use RuntimeException;

trait VarsOrchestratorTrait
{
    // //--- Internal Caches (Global & Contextual) //---
    protected ?Collection $globalTreeAllEntities = null;
    protected ?Collection $globalFlattenedAllEntities = null;
    protected Collection $contextualCachedEntities; // Key: contextKey, Value: Collection of flattened entities

    // //--- Current Filter State (Transient per query chain) //---
    protected ?int $currentDepthFilterLevel = null;
    protected ?string $currentUserFilterId = null;
    protected array $currentExcludedContextKeys = []; // <--- CAMBIO: Inicializamos como array vacío
    protected array $currentIncludedContextKeys = []; // <--- CAMBIO: Inicializamos como array vacío

    // //--- Filtered Result Cache (Per Query Chain) //---
    protected ?Collection $filteredEntitiesCache = null;

    /**
     * Constructor del Orchestrator.
     * Añadimos aquí la inicialización de currentIncludedContextKeys con todos los contextos.
     */
    public function __construct()
    {
        $this->contextualCachedEntities = new Collection();
        $this->resetQueryState(); // Asegura que los filtros están limpios al inicio

        // Cargar las configuraciones de los contextos al instanciar el orquestador base.
        // Esto es esencial para que getContextKeys() y getContextInstance() funcionen.
        $this->loadAllContextConfigurations(); // Este método debe estar en BaseOrchestrator y cargar $this->contextConfigurations

        // <--- NUEVO: Inicializar currentIncludedContextKeys con todos los contextos al inicio.
        // Esto garantiza que siempre esté poblado y nunca sea null.
         $this->currentIncludedContextKeys = $this->getContextKeys(); 
    }

    /**
     * Resets the current query state of the orchestrator.
     * This should be called by `newQuery()` in the concrete orchestrator.
     * @return void
     */
    protected function resetQueryState(): void
    {
        $this->currentDepthFilterLevel = null;
        $this->currentUserFilterId = null;
        $this->currentExcludedContextKeys = []; // <--- CAMBIO: Reiniciar como array vacío
        // <--- IMPORTANTE: No se setea a null aquí.
        // La lógica de newQuery() o el constructor se encargarán de inicializarlo con todos los contextos.
        $this->currentIncludedContextKeys = []; // <--- CAMBIO: Reiniciar como array vacío
        $this->filteredEntitiesCache = null; 
    }

    /**
     * Gets the raw, unfiltered entities from a specific context,
     * caching them for future use.
     *
     * @param string $contextKey
     * @return Collection
     * @throws RuntimeException
     */
    protected function getRawEntitiesByContext(string $contextKey): Collection
    {
        if (!$this->contextualCachedEntities->has($contextKey)) {
            try {
                $context = $this->getContextInstance($contextKey);
                $this->contextualCachedEntities->put($contextKey, $context->getAllFlattenedRoutes());
            } catch (RuntimeException $e) {
                throw new RuntimeException("Error loading context '{$contextKey}': " . $e->getMessage(), 0, $e);
            }
        }
        return $this->contextualCachedEntities->get($contextKey);
    }

    // //--- Filter Configuration Methods (Return $this for chaining) //---

    public function withDepth(?int $level = null): static
    {
        $this->currentDepthFilterLevel = $level;
        $this->filteredEntitiesCache = null; 
        return $this;
    }

    public function forUser(?string $userId): static
    {
        $this->currentUserFilterId = $userId;
        $this->filteredEntitiesCache = null; 
        return $this;
    }

    public function loadContexts(string|array $contextKeys): static
    {
        $this->currentIncludedContextKeys = (array) $contextKeys; // <--- CAMBIO: Directamente asignamos
        //dd($this->currentIncludedContextKeys);
        $this->currentExcludedContextKeys = []; // <--- CAMBIO: Limpiamos las exclusiones
        $this->filteredEntitiesCache = null; 
        return $this;
    }

    public function loadAllContexts(): static
    {
        // <--- CAMBIO: Setear a TODAS las claves de contexto disponibles
        $this->currentIncludedContextKeys = $this->getContextKeys(); 
        $this->currentExcludedContextKeys = []; // <--- CAMBIO: Limpiamos las exclusiones
        $this->filteredEntitiesCache = null; 
        return $this;
    }

    public function resetContexts(): static
    {
        // <--- CAMBIO: Resetear a TODAS las claves de contexto disponibles
        $this->currentIncludedContextKeys = $this->getContextKeys();
        $this->currentExcludedContextKeys = []; // <--- CAMBIO: Limpiamos las exclusiones
        $this->filteredEntitiesCache = null; 
        return $this;
    }

    public function excludeContexts(string|array $contextKeys): static
    {
        $excluded = (array) $contextKeys;
        $allContexts = $this->getContextKeys();
        // <--- CAMBIO: currentIncludedContextKeys serán todas las claves excepto las excluidas
        $this->currentIncludedContextKeys = array_values(array_diff($allContexts, $excluded)); 
        $this->currentExcludedContextKeys = []; // <--- CAMBIO: Limpiamos las exclusiones (ya no son necesarias para la lógica)
        $this->filteredEntitiesCache = null; 
        return $this;
    }

    public function resetFilters(): static
    {
        $this->resetQueryState();
        // <--- IMPORTANTE: Después de resetQueryState, volvemos a inicializar con todos los contextos
        $this->currentIncludedContextKeys = $this->getContextKeys(); 
        return $this;
    }

    /**
     * Prepares the orchestrator to filter for the current authenticated user.
     * @param string|null $userId If null, attempts to get the authenticated user's ID.
     * @return static
     * @throws RuntimeException If no user ID can be determined.
     */
    public function prepareForUser(?string $userId = null): static
    {
        if ($userId === null) {
            $user = auth()->user();
            if (!$user) {
                throw new RuntimeException("No authenticated user found to prepare filters for.");
            }
            $userId = $user->id;
        }
        return $this->forUser($userId);
    }

    //---

    ### **Data Retrieval Methods (Apply filters and return data)**

    /**
     * Obtiene el árbol de entidades completo o filtrado por contextos si se han especificado.
     * No aplica filtros de usuario ni de profundidad. Este método ahora siempre respeta
     * el estado de `currentIncludedContextKeys`.
     *
     * @return Collection El árbol de entidades resultante de los contextos activos.
     */
    public function all(): Collection
    {
        // Ahora, 'all()' simplemente usa getFlattenedEntitiesByActiveContexts() que ya
        // se basa en currentIncludedContextKeys.
        $flattenedEntities = $this->getFlattenedEntitiesByActiveContexts();
        
        return $this->buildTreeFromFlattened($flattenedEntities);
    }

    /**
     * Obtiene el árbol de entidades aplicando todos los filtros configurados
     * (usuario, profundidad, contextos incluidos/excluidos).
     * Este es el método final para obtener el resultado de una query.
     *
     * @return Collection El árbol de entidades filtrado.
     */
    public function get(): Collection
    {
        if ($this->filteredEntitiesCache !== null) {
            return $this->filteredEntitiesCache;
        }

     //   dd($this->currentIncludedContextKeys);
        
        // Paso 1: Cargar todas las entidades aplanadas de los contextos relevantes.
        // Esto respeta currentIncludedContextKeys (que ahora siempre es un array poblado).
        $flattenedEntities = $this->getFlattenedEntitiesByActiveContexts();
        
        // Paso 2: Reconstruir el árbol a partir de las entidades aplanadas.
        $tree = $this->buildTreeFromFlattened($flattenedEntities);

        // Paso 3: Aplicar filtro de usuario/permisos y marcar nodos activos (condicionalmente)
        if ($this->currentUserFilterId !== null) {
            $user = User::find($this->currentUserFilterId);
            if ($user) {
                $permissions = $user->roles->flatMap(fn($role) => $role->permissions->pluck('name'))
                    ->unique()->values()->all();
                $tree = $this->applyPermissionAndActiveFilter($tree, $permissions, request()->route()?->getName());
            } else {
                $tree = collect();
            }
        } else {
            $tree = $this->markActiveNodesAndSetGroupUrls($tree, request()->route()?->getName());
        }

        // Paso 4: Aplicar filtro de profundidad
        if ($this->currentDepthFilterLevel !== null) {
            $tree = $this->filterTreeByDepth($tree, 0, $this->currentDepthFilterLevel);
        }

        $this->filteredEntitiesCache = $tree;
        
        return $this->filteredEntitiesCache;
    }

    /**
     * Recursively marks active nodes and sets group URLs based on children,
     * without applying permission filters.
     *
     * @param Collection|array $nodes The nodes of the tree to process.
     * @param string|null $activeRouteName El nombre de la ruta activa actual.
     * @return Collection The processed tree with active nodes marked and group URLs set.
     */
    protected function markActiveNodesAndSetGroupUrls(array|Collection $nodes, ?string $activeRouteName = null): Collection
    {
        $processed = collect();

        foreach ($nodes as $node) {
            $clonedNode = clone $node;

            // Procesar hijos recursivamente
            $children = $this->markActiveNodesAndSetGroupUrls($clonedNode->getChildrens(), $activeRouteName);

            // Si es un grupo y tiene hijos, intenta establecer su URL
            if ($clonedNode->isGroup && $children->isNotEmpty()) {
                $this->setGroupUrlFromFirstChild($clonedNode, $children);
            }

            // Marcar el nodo como activo si su URL o ID coincide con la ruta activa, o si alguno de sus hijos está activo
            $nodeIsActive = $clonedNode->getUrlName() === $activeRouteName || $clonedNode->getId() === $activeRouteName;
            $childrenAreActive = $children->contains(fn($child) => $child->isActive());
            $clonedNode->setIsActive($nodeIsActive || $childrenAreActive);

            // Reconstruir la colección de hijos del nodo clonado
            $clonedNode->setChildrens(new Collection());
            foreach ($children as $child) {
                $clonedNode->addChild($child);
            }
            $processed->push($clonedNode);
        }
        return $processed;
    }

    /**
     * Obtiene el árbol de entidades filtrado para el usuario autenticado actual.
     * Es un atajo para `prepareForUser()->get()`.
     *
     * @return Collection
     * @throws RuntimeException Si no hay usuario autenticado.
     */
    public function getForCurrentUser(): Collection
    {
        $this->prepareForUser();
        return $this->get(); 
    }

    /**
     * Gets all entities in a flattened structure, considering active context filters.
     * Does NOT apply depth or user/permission filters, as those are for tree structures.
     * This method will use `currentIncludedContextKeys`.
     *
     * @return Collection
     */
    public function allFlattened(): Collection
    {
        // Este método ahora se encarga de cargar entidades según los filtros de contexto
        // y se llama desde `get()` para la construcción del árbol inicial.
        return $this->getFlattenedEntitiesByActiveContexts();
    }

    /**
     * Helper method to get flattened entities based on current context filters.
     * Ahora, este método siempre usa `currentIncludedContextKeys` que se garantiza que es un array.
     * @return Collection
     */
    protected function getFlattenedEntitiesByActiveContexts(): Collection
    {
        $flattened = collect();
//dd($this->currentIncludedContextKeys);
        // <--- CAMBIO: Iteramos directamente sobre currentIncludedContextKeys
        foreach ($this->currentIncludedContextKeys as $key) {
            try {
                $flattened = $flattened->merge($this->getRawEntitiesByContext($key));
            } catch (RuntimeException $e) {
                // Log o maneja el error si un contexto no puede ser cargado
            }
        }
        return $flattened;
    }

    /**
     * Obtiene una sub-rama (sub-árbol) de entidades a partir de un ID de entidad raíz,
     * aplicando todos los filtros configurados en la instancia actual del orquestador.
     *
     * @param string $rootEntityId El ID de la entidad que será la raíz de la sub-rama.
     * @return Collection Una colección con la sub-rama, o vacía si no se encuentra la entidad raíz.
     */
    public function getSubBranch(string $rootEntityId): Collection
    {
        $fullFilteredTree = $this->get();

        $flattenedFilteredTree = $this->flattenTree($fullFilteredTree);
        
        if (!$flattenedFilteredTree->has($rootEntityId)) {
            return collect();
        }

        $rootEntity = clone $flattenedFilteredTree->get($rootEntityId);
        $rootEntity->setParentId(null);
        $rootEntity->setChildrens(new Collection()); 

        $subTree = collect([$rootEntity]);
        $this->buildSubTreeRecursive($rootEntity, $flattenedFilteredTree);

        return $subTree;
    }

    //---

    ### **Core Internal Tree Operations**

    /**
     * Gets the raw, unfiltered global tree from ALL available contexts.
     * Ignora cualquier filtro de inclusión/exclusión de contexto aplicado.
     * Se utiliza para operaciones que requieren la totalidad de las rutas.
     * @return Collection
     */
    protected function getRawGlobalTree(): Collection
    {
        if ($this->globalTreeAllEntities === null) {
            $flattened = $this->getRawGlobalFlattened(); 
            $this->globalTreeAllEntities = $this->buildTreeFromFlattened($flattened);
        }
        return $this->globalTreeAllEntities;
    }

    /**
     * Gets the raw, unfiltered global flattened list by loading ALL contexts.
     * Ignora cualquier filtro de inclusión/exclusión de contexto aplicado.
     * @return Collection
     */
    protected function getRawGlobalFlattened(): Collection
    {
        if ($this->globalFlattenedAllEntities === null) {
            $this->globalFlattenedAllEntities = collect();
            foreach ($this->getContextKeys() as $key) { 
                try {
                    $this->globalFlattenedAllEntities = $this->globalFlattenedAllEntities->merge($this->getRawEntitiesByContext($key));
                } catch (RuntimeException $e) {
                    // Log o maneja el error si un contexto no puede ser cargado
                }
            }
        }
        return $this->globalFlattenedAllEntities;
    }

    /**
     * Builds a tree from a flattened collection of entities.
     * Handles 'makeSelf' entities.
     *
     * @param Collection $flat A flattened collection of FpEntityInterface.
     * @return Collection The built tree.
     */
    public function buildTreeFromFlattened(Collection $flat): Collection
    {
        $entitiesById = collect();

        foreach ($flat as $entity) {
            $clonedEntity = clone $entity;
            $clonedEntity->setChildrens(new Collection());

            if ($clonedEntity->getMakerMethod() === 'makeSelf') {
                $originalId = $clonedEntity->getId();
                $sourceEntity = $this->getRawGlobalFlattened()->get($clonedEntity->getInstanceRouteId());

                if (!$sourceEntity) {
                    continue;
                }
                $clonedSource = clone $sourceEntity;
                $clonedSource->setId($originalId);
                $clonedSource->setMakerMethod('makeSelf');
                $clonedEntity = $clonedSource;
                $clonedEntity->setChildrens(new Collection());
            }
            $entitiesById->put($clonedEntity->getId(), $clonedEntity);
        }

        $tree = collect();
        foreach ($entitiesById as $id => $entity) {
            $parentId = $entity->getParentId();
            if ($parentId !== null && $entitiesById->has($parentId)) {
                $parent = $entitiesById->get($parentId);
                $parent->addChild($entity);
            } else {
                $tree->push($entity);
            }
        }
        return $tree;
    }

    /**
     * Recursive helper to build a sub-tree from a flattened collection.
     *
     * @param FpEntityInterface $currentNode The current node to build children for.
     * @param Collection $allEntitiesFlattened All entities in flattened form (from the source tree).
     */
    protected function buildSubTreeRecursive(FpEntityInterface $currentNode, Collection $allEntitiesFlattened): void
    {
        foreach ($allEntitiesFlattened as $entity) {
            if ($entity->getParentId() === $currentNode->getId()) {
                $clonedChild = clone $entity;
                $clonedChild->setChildrens(new Collection());
                $currentNode->addChild($clonedChild);
                $this->buildSubTreeRecursive($clonedChild, $allEntitiesFlattened);
            }
        }
    }

    /**
     * Filters a tree by maximum depth.
     *
     * @param Collection|array $nodes The nodes of the tree to filter.
     * @param int $currentLevel The current level (starts at 0).
     * @param int $maxDepth The maximum allowed depth.
     * @return Collection The depth-filtered tree.
     */
    protected function filterTreeByDepth(Collection|array $nodes, int $currentLevel, int $maxDepth): Collection
    {
        $filteredNodes = new Collection();

        foreach ($nodes as $node) {
            if ($currentLevel <= $maxDepth) {
                $clonedNode = clone $node;
                $clonedNode->setChildrens(new Collection()); 

                if ($currentLevel < $maxDepth) {
                    $filteredChildren = $this->filterTreeByDepth($node->getChildrens(), $currentLevel + 1, $maxDepth);
                    foreach ($filteredChildren as $child) {
                        $clonedNode->addChild($child);
                    }
                }
                $filteredNodes->push($clonedNode);
            }
        }
        return $filteredNodes;
    }

    /**
     * Recursively filters a tree by user permissions and marks active nodes.
     *
     * @param Collection|array $nodes The nodes of the tree to filter.
     * @param array $allowedPermissions Permisos permitidos para el usuario actual.
     * @param string|null $activeRouteName El nombre de la ruta activa actual.
     * @return Collection El árbol filtrado y con nodos activos marcados.
     */
    protected function applyPermissionAndActiveFilter(array|Collection $nodes, array $allowedPermissions, ?string $activeRouteName = null): Collection
    {
        $filtered = collect();

        foreach ($nodes as $node) {
            $clonedNode = clone $node;

            $children = $this->applyPermissionAndActiveFilter($clonedNode->getChildrens(), $allowedPermissions, $activeRouteName);

            if ($clonedNode->isGroup && $children->isNotEmpty()) {
                $this->setGroupUrlFromFirstChild($clonedNode, $children);
            }

            $nodeIsActive = $clonedNode->getUrlName() === $activeRouteName || $clonedNode->getId() === $activeRouteName;
            $childrenAreActive = $children->contains(fn($child) => $child->isActive());
            $clonedNode->setIsActive($nodeIsActive || $childrenAreActive);

            $hasPermission = $this->hasValidPermission($clonedNode, $allowedPermissions);
            $shouldInclude = ($hasPermission || $clonedNode->isGroup || $children->isNotEmpty());

            if (!$shouldInclude && !$clonedNode->isGroup) {
                continue;
            }

            if ($clonedNode->isGroup && $children->isEmpty() && !$hasPermission) {
                continue;
            }

            $clonedNode->setChildrens(new Collection()); 
            foreach ($children as $child) {
                $clonedNode->addChild($child);
            }
            $filtered->push($clonedNode);
        }
        return $filtered;
    }

    protected function setGroupUrlFromFirstChild($node, Collection $children): void
    {
        $firstChildWithUrl = $children->first(fn($child) => $child->getUrl() !== null);
        if ($firstChildWithUrl) {
            $node->setUrl($firstChildWithUrl->getUrl());
            $node->setUrlName($firstChildWithUrl->getUrlName());
        }
    }

    protected function hasValidPermission($node, array $allowedPermissions): bool
    {
        if ($node->isGroup) {
            return is_null($node->accessPermission) || in_array($node->accessPermission, $allowedPermissions);
        }
        return is_null($node->accessPermission) || in_array($node->accessPermission, $allowedPermissions);
    }

    //---

    ### **Unified Breadcrumbs and Active Branch Logic**

    /**
     * Gets the breadcrumbs for a specific entity from the tree resulting from the current query chain.
     *
     * @param FpEntityInterface $entity The entity for which to get breadcrumbs.
     * @return Collection A collection of entities representing the breadcrumbs.
     */
    public function getBreadcrumbs(FpEntityInterface $entity): Collection
    {
        $sourceTree = $this->get();

        $targetId = $entity->getId();
        $flattenedSource = $this->flattenTree($sourceTree);

        $breadcrumbs = new Collection();
        $currentId = $targetId;

        while ($currentId !== null && $flattenedSource->has($currentId)) {
            $currentEntity = $flattenedSource->get($currentId);
            $breadcrumbs->prepend($currentEntity);
            $currentId = $currentEntity->getParentId();
        }

        return $breadcrumbs;
    }

    /**
     * Gets the active branch (the current entity and its active ancestors/descendants)
     * from the tree resulting from the current query chain.
     *
     * @param string|null $activeRouteName The name of the active route. If null, attempts to get from request.
     * @return Fp\FullRoute\Contracts\FpEntityInterface|null The root entity of the active branch, or null if not found.
     */
    public function getActiveBranch(?string $activeRouteName = null): ?FpEntityInterface
    {
        $activeRouteName = $activeRouteName ?? request()->route()?->getName();
        if (!$activeRouteName) {
            return null;
        }

        $sourceTree = $this->get();

        $flattenedSource = $this->flattenTree($sourceTree);

        if (!$flattenedSource->has($activeRouteName)) {
            return null;
        }

        $activeNode = $flattenedSource->get($activeRouteName);

        $rootOfActiveBranch = $activeNode;
        $current = $activeNode;

        while ($current->getParentId() !== null) {
            $parent = $flattenedSource->get($current->getParentId());
            if ($parent && $parent->isActive) {
                $rootOfActiveBranch = $parent;
                $current = $parent;
            } else {
                break;
            }
        }
        return $rootOfActiveBranch;
    }

    /**
     * Flattens a tree (Collection of entities) for easy lookup by ID.
     * @param Collection $tree The tree to flatten.
     * @return Collection A flattened collection of entities, keyed by ID.
     */
    public function flattenTree(Collection $tree): Collection
    {
        $flattened = collect();
        foreach ($tree as $node) {
            $flattened->put($node->getId(), $node);
            if ($node->getChildrens()->isNotEmpty()) {
                $flattened = $flattened->merge($this->flattenTree($node->getChildrens()));
            }
        }
        return $flattened;
    }
}