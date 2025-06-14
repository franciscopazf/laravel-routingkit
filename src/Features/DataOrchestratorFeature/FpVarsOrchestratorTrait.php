<?php

namespace Fp\RoutingKit\Features\DataOrchestratorFeature;

use App\Models\User;
use Fp\RoutingKit\Contracts\FpEntityInterface;
use Illuminate\Support\Collection;
use RuntimeException;

trait FpVarsOrchestratorTrait
{
    // //--- Internal Caches (Global & Contextual) //---
    protected ?Collection $globalTreeAllEntities = null;
    protected ?Collection $globalFlattenedAllEntities = null;
    protected Collection $contextualCachedEntities; // Key: contextKey, Value: Collection of flattened entities

    // //--- Current Filter State (Transient per query chain) //---
    protected ?int $currentDepthFilterLevel = null;
    protected ?string $currentUserFilterId = null;
    protected array $currentExcludedContextKeys = [];
    protected array $currentIncludedContextKeys = [];
    protected bool $forceEmptyGroups = false; 

    // //--- Filtered Result Cache (Per Query Chain) //---
    protected ?Collection $filteredEntitiesCache = null;

    // //--- Observer Callback ---
    protected ?\Closure $contextKeysObserver = null;

    /**
     * Constructor del Orchestrator.
     * NOTA: Este constructor ahora se llama desde el constructor de BaseOrchestrator.
     * Asegúrate de que `loadAllContextConfigurations()` se llame antes de `setIncludedContextKeys($this->getContextKeys())`.
     */
    public function __construct()
    {
        $this->contextualCachedEntities = new Collection();
        $this->resetQueryState(); 
    }

    /**
     * Inicia una nueva "query" en el orquestador, asegurando que los filtros temporales sean limpiados.
     * Proporciona un punto de entrada claro para iniciar cadenas de filtros.
     * @return static
     */
    public function newQuery(): static
    {
        $this->resetQueryState(); // Reinicia el estado de la consulta
        // Después de resetear el estado, asegúrate de que los contextos incluidos estén en su estado inicial.
        // Esto es importante para que una nueva consulta comience con todos los contextos por defecto.
        $this->setIncludedContextKeys($this->getContextKeys());
        return $this;
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
        $this->currentExcludedContextKeys = [];
        $this->forceEmptyGroups = false; // Reinicia el estado de grupos vacíos
        $this->filteredEntitiesCache = null;
    }

    /**
     * Establece las claves de contexto incluidas y notifica al observador.
     * @param array $keys
     * @return void
     */
    protected function setIncludedContextKeys(array $keys): void
    {
        // Solo actualizar si realmente ha cambiado para evitar ecos redundantes
        if ($this->currentIncludedContextKeys !== $keys) {
            $this->currentIncludedContextKeys = $keys;
            if ($this->contextKeysObserver) {
                // Llama al observador con las nuevas claves
                ($this->contextKeysObserver)($this->currentIncludedContextKeys);
            }
        }
    }

    /**
     * Obtiene las claves de contexto incluidas actualmente.
     * @return array
     */
    public function getCurrentIncludedContextKeys(): array
    {
        return $this->currentIncludedContextKeys;
    }

    /**
     * Registra una función de callback para observar los cambios en currentIncludedContextKeys.
     *
     * @param \Closure $callback La función a ejecutar cuando currentIncludedContextKeys cambie.
     * Recibe el array de las nuevas claves de contexto.
     * @return static
     */
    public function observeIncludedContextKeys(\Closure $callback): static
    {
        $this->contextKeysObserver = $callback;
        return $this;
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
        // Carga perezosa y caché: Si no está cacheado, lo carga.
        if (!$this->contextualCachedEntities->has($contextKey)) {

            $context = $this->getContextInstance($contextKey);

            $this->contextualCachedEntities->put($contextKey, $context->getFlattenedEntitys());
        }
        return $this->contextualCachedEntities->get($contextKey);
    }

    // //--- Filter Configuration Methods (Return $this for chaining) //---

    /**
     * Establece el nivel de profundidad máxima para filtrar el árbol.
     * @param int|null $level El nivel de profundidad máxima. `null` para no aplicar filtro de profundidad.
     * @return static
     */
    public function withDepth(?int $level = null): static
    {
        $this->currentDepthFilterLevel = $level;
        $this->filteredEntitiesCache = null; // Invalida la caché del resultado final
        return $this;
    }

    /**
     * Configura el filtro para un usuario específico.
     * @param string|null $userId El ID del usuario. `null` para no aplicar filtro de usuario.
     * @return static
     */
    public function forUser(?string $userId): static
    {
        $this->currentUserFilterId = $userId;
        $this->filteredEntitiesCache = null; // Invalida la caché del resultado final
        return $this;
    }

    /**
     * Carga y activa contextos específicos.
     * @param string|array $contextKeys Una o más claves de contexto a cargar y activar.
     * @return static
     */
    public function loadContexts(string|array $contextKeys): static
    {
        $this->setIncludedContextKeys((array) $contextKeys);
        $this->currentExcludedContextKeys = []; // Limpiamos las exclusiones
        $this->filteredEntitiesCache = null; // Invalida la caché
        return $this;
    }

    /**
     * Carga y activa todos los contextos disponibles.
     * @return static
     */
    public function loadAllContexts(): static
    {
        $this->setIncludedContextKeys($this->getContextKeys());
        $this->currentExcludedContextKeys = []; // Limpiamos las exclusiones
        $this->filteredEntitiesCache = null; // Invalida la caché
        return $this;
    }

    /**
     * Reinicia los contextos activos a su estado por defecto (generalmente todos los disponibles).
     * @return static
     */
    public function resetContexts(): static
    {
        $this->setIncludedContextKeys($this->getContextKeys());
        $this->currentExcludedContextKeys = []; // Limpiamos las exclusiones
        $this->filteredEntitiesCache = null; // Invalida la caché
        return $this;
    }

    /**
     * Excluye contextos específicos. Los demás se mantienen activos.
     * @param string|array $contextKeys Una o más claves de contexto a excluir.
     * @return static
     */
    public function excludeContexts(string|array $contextKeys): static
    {
        $excluded = (array) $contextKeys;
        $allContexts = $this->getContextKeys();
        $this->setIncludedContextKeys(array_values(array_diff($allContexts, $excluded)));
        $this->currentExcludedContextKeys = []; // Limpiamos las exclusiones
        $this->filteredEntitiesCache = null; // Invalida la caché
        return $this;
    }

    /**
     * Reinicia todos los filtros configurados en la instancia actual del orquestador.
     * @return static
     */
    public function resetFilters(): static
    {
        $this->resetQueryState();
        $this->setIncludedContextKeys($this->getContextKeys()); // Vuelve a inicializar con todos los contextos
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

    //--- NUEVOS FILTROS SOLICITADOS ---

    /**
     * Filtra para mostrar solo los archivos de un contexto específico (o varios).
     * Esto carga solo los contextos indicados.
     *
     * @param string|array $contextKeys La clave o claves de contexto a incluir.
     * @return static
     */
    public function filterOnlyFiles(string|array $contextKeys): static
    {
        return $this->loadContexts($contextKeys);
    }

    /**
     * Filtra para mostrar todas las rutas/archivos de todos los contextos disponibles.
     * Es un alias de `loadAllContexts()`.
     * @return static
     */
    public function filterAllFiles(): static
    {
        return $this->loadAllContexts();
    }

    /**
     * Configura el orquestador para filtrar las rutas para el usuario autenticado actual.
     * Es un alias de `prepareForUser()`.
     * @return static
     * @throws RuntimeException Si no hay usuario autenticado.
     */
    public function filterForCurrentUser(): static
    {
        return $this->prepareForUser();
    }

    /**
     * Configura el filtro de profundidad. Alias de `withDepth()`.
     * @param int|null $level El nivel de profundidad.
     * @return static
     */
    public function filterByDepth(?int $level = null): static
    {
        return $this->withDepth($level);
    }

    /**
     * Controla si los nodos grupo sin ítems (hijos) se deben incluir en el resultado final.
     * Por defecto, no se incluyen a menos que se llame a este método con `true`.
     *
     * @param bool $value `true` para forzar la inclusión de grupos vacíos, `false` para omitirlos.
     * El valor por defecto es `true` para facilitar la activación.
     * @return static
     */
    public function withEmptyGroups(bool $value = true): static
    {
        $this->forceEmptyGroups = $value;
        $this->filteredEntitiesCache = null; // Invalida la caché
        return $this;
    }


    //---

    ### **Data Retrieval Methods (Apply filters and return data)**

    /**
     * Obtiene el árbol de entidades completo o filtrado por contextos si se han especificado.
     * No aplica filtros de usuario ni de profundidad. Este método ahora siempre respeta
     * el estado de `currentIncludedContextKeys`. Siempre devuelve todos los grupos, incluidos los vacíos.
     *
     * @return Collection El árbol de entidades resultante de los contextos activos.
     */
    public function all(): Collection
    {
         
        if (empty($this->currentIncludedContextKeys) && !empty($this->getContextKeys())) {
           
            $this->setIncludedContextKeys($this->getContextKeys());
        }

        $flattenedEntities = $this->getFlattenedEntitiesByActiveContexts();

        $tree = $this->buildTreeFromFlattened($flattenedEntities);
       
        return $tree;
    }

    /**
     * Obtiene el árbol de entidades aplicando todos los filtros configurados
     * (usuario, profundidad, contextos incluidos/excluidos, grupos vacíos).
     * Este es el método final para obtener el resultado de una query.
     *
     * @return Collection El árbol de entidades filtrado.
     */
    public function get(): Collection
    {
        // Si ya tenemos un resultado cacheado para los filtros actuales, lo devolvemos.
        if ($this->filteredEntitiesCache !== null) {
            return $this->filteredEntitiesCache;
        }

        // Si currentIncludedContextKeys está vacío al llamar a get(), significa que no se especificaron contextos
        // explícitamente a través de loadContexts o excludeContexts. En este caso, asumimos que
        // se deben cargar todos los contextos disponibles.
        if (empty($this->currentIncludedContextKeys) && !empty($this->getContextKeys())) {
            $this->setIncludedContextKeys($this->getContextKeys());
        }

        // Paso 1: Cargar todas las entidades aplanadas de los contextos relevantes.
        // Esto respeta currentIncludedContextKeys.
        $flattenedEntities = $this->getFlattenedEntitiesByActiveContexts();

        // Paso 2: Reconstruir el árbol a partir de las entidades aplanadas.
        $tree = $this->buildTreeFromFlattened($flattenedEntities);

        // Paso 3: Aplicar filtro de usuario/permisos y marcar nodos activos
        if ($this->currentUserFilterId !== null) {
            $user = User::find($this->currentUserFilterId);
            if ($user) {
                $permissions = $user->roles->flatMap(fn($role) => $role->permissions->pluck('name'))
                    ->unique()->values()->all();
                $tree = $this->applyPermissionAndActiveFilter($tree, $permissions, request()->route()?->getName(), $this->forceEmptyGroups);
            } else {
                $tree = collect(); // Si el usuario no existe, no hay permisos, por lo tanto, no hay rutas
            }
        } else {
            // Si no hay filtro de usuario, aplicamos la marcación de activos y la lógica de grupos.
            $tree = $this->markActiveNodesAndSetGroupUrls($tree, request()->route()?->getName(), $this->forceEmptyGroups);
        }

        // Paso 4: Aplicar filtro de profundidad
        if ($this->currentDepthFilterLevel !== null) {
            $tree = $this->filterTreeByDepth($tree, 0, $this->currentDepthFilterLevel);
        }

        // Cacheamos el resultado final antes de devolverlo
        $this->filteredEntitiesCache = $tree;

        return $this->filteredEntitiesCache;
    }

    /**
     * Recursively marks active nodes and sets group URLs based on their child items,
     * considering whether empty groups should be included.
     *
     * @param Collection|array $nodes The nodes of the tree to process.
     * @param string|null $activeRouteName The name of the active route.
     * @param bool $forceEmptyGroups If true, groups with no items will be included.
     * @return Collection The processed tree with active nodes marked and group URLs set.
     */
    protected function markActiveNodesAndSetGroupUrls(array|Collection $nodes, ?string $activeRouteName = null, bool $forceEmptyGroups = false): Collection
    {
        $processed = collect();

        foreach ($nodes as $node) {
            $clonedNode = clone $node;

            // Procesar items recursivamente
            $items = $this->markActiveNodesAndSetGroupUrls($clonedNode->getItems(), $activeRouteName, $forceEmptyGroups);

            // Si es un grupo y tiene items, intenta establecer su URL
            if ($clonedNode->isGroup && $items->isNotEmpty()) {
                $this->setGroupUrlFromFirstChild($clonedNode, $items);
            }

            // Marcar el nodo como activo si su URL o ID coincide con la ruta activa, o si alguno de sus items está activo
            $nodeIsActive = ($clonedNode->getUrlName() && $clonedNode->getUrlName() === $activeRouteName) || $clonedNode->getId() === $activeRouteName;
            $itemsAreActive = $items->contains(fn($item) => $item->isActive());
            $clonedNode->setIsActive($nodeIsActive || $itemsAreActive);

            // Lógica para grupos vacíos:
            // Si es un grupo y después de procesar los ítems, no tiene ítems (está vacío)
            // Y la bandera forceEmptyGroups es FALSE (por defecto, se omite).
            if ($clonedNode->isGroup && $items->isEmpty() && !$forceEmptyGroups) {
                continue; // Omitir este grupo vacío
            }

            // Reconstruir la colección de items del nodo clonado
            $clonedNode->setItems(new Collection());
            foreach ($items as $item) {
                $clonedNode->addItem($item);
            }
            $processed->push($clonedNode);
        }
        return $processed;
    }

    /**
     * Obtiene el árbol de entidades filtrado para el usuario autenticado actual.
     * Es un atajo para `filterForCurrentUser()->get()`.
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

        foreach ($this->currentIncludedContextKeys as $key) {

            $flattened = $flattened->merge($this->getRawEntitiesByContext($key));
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
        // Obtiene el árbol completo filtrado por el estado actual.
        // Si ya está cacheado, se utiliza la caché, optimizando llamadas múltiples.
        $fullFilteredTree = $this->get();

        $flattenedFilteredTree = $this->flattenTree($fullFilteredTree);

        if (!$flattenedFilteredTree->has($rootEntityId)) {
            return collect();
        }

        // Clonar la entidad raíz para asegurar que no modificamos el árbol original cacheado.
        $rootEntity = clone $flattenedFilteredTree->get($rootEntityId);
        $rootEntity->setParentId(null); // La nueva raíz no tiene padre en su nueva sub-estructura.
        $rootEntity->setItems(new Collection()); // Limpiar ítems para reconstruir.

        $subTree = collect([$rootEntity]);
        $this->buildSubTreeRecursive($rootEntity, $flattenedFilteredTree);

        return $subTree;
    }

    /**
     * Obtiene múltiples subramas a partir de un arreglo de IDs de entidades raíz.
     * Aplica todos los filtros configurados en la instancia actual del orquestador.
     *
     * @param array $rootEntityIds Un arreglo de IDs de entidades que serán las raíces de las subramas.
     * @return Collection Una colección de colecciones, donde cada sub-colección es una subrama.
     */
    public function getSubBranches(array $rootEntityIds): Collection
    {
        $allSubBranches = collect();
        // Obtiene el árbol completo filtrado una sola vez para optimizar las búsquedas de subramas.
        $fullFilteredTree = $this->get();
        $flattenedFilteredTree = $this->flattenTree($fullFilteredTree);

        foreach ($rootEntityIds as $rootId) {
            if (!$flattenedFilteredTree->has($rootId)) {
                // Si la raíz no existe, simplemente la saltamos o podrías añadir un registro de error.
                continue;
            }

            // Clonar la entidad raíz para construir la subrama.
            $rootEntity = clone $flattenedFilteredTree->get($rootId);
            $rootEntity->setParentId(null);
            $rootEntity->setItems(new Collection());

            $subTree = collect([$rootEntity]);
            // Reconstruir recursivamente la subrama
            $this->buildSubTreeRecursive($rootEntity, $flattenedFilteredTree);

            // Añadir la subrama al resultado principal
            $allSubBranches->push($subTree->first()); // Obtenemos el primer elemento que es la raíz de la subrama
        }

        return $allSubBranches;
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
        // Carga perezosa para el árbol global completo
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
        // Carga perezosa para la lista aplanada global completa
        if ($this->globalFlattenedAllEntities === null) {
            $this->globalFlattenedAllEntities = collect();
            foreach ($this->getContextKeys() as $key) {
                $this->globalFlattenedAllEntities = $this->globalFlattenedAllEntities->merge($this->getRawEntitiesByContext($key));
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
            $clonedEntity->setItems(new Collection());

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
                $clonedEntity->setItems(new Collection());
            }
            $entitiesById->put($clonedEntity->getId(), $clonedEntity);
        }

        $tree = collect();
        foreach ($entitiesById as $id => $entity) {
            $parentId = $entity->getParentId();
            if ($parentId !== null && $entitiesById->has($parentId)) {
                $parent = $entitiesById->get($parentId);
                $parent->addItem($entity);
            } else {
                $tree->push($entity);
            }
        }
        return $tree;
    }

    /**
     * Recursive helper to build a sub-tree from a flattened collection.
     *
     * @param FpEntityInterface $currentNode The current node to build items for.
     * @param Collection $allEntitiesFlattened All entities in flattened form (from the source tree).
     */
    protected function buildSubTreeRecursive(FpEntityInterface $currentNode, Collection $allEntitiesFlattened): void
    {
        foreach ($allEntitiesFlattened as $entity) {
            if ($entity->getParentId() === $currentNode->getId()) {
                $clonedChild = clone $entity;
                $clonedChild->setItems(new Collection());
                $currentNode->addItem($clonedChild);
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
                $clonedNode->setItems(new Collection());

                if ($currentLevel < $maxDepth) {
                    $filteredItems = $this->filterTreeByDepth($node->getItems(), $currentLevel + 1, $maxDepth);
                    foreach ($filteredItems as $item) {
                        $clonedNode->addItem($item);
                    }
                }
                $filteredNodes->push($clonedNode);
            }
        }
        return $filteredNodes;
    }

    /**
     * Recursively filters a tree by user permissions and marks active nodes,
     * considering whether empty groups should be included.
     *
     * @param Collection|array $nodes The nodes of the tree to filter.
     * @param array $allowedPermissions Permisos permitidos para el usuario actual.
     * @param string|null $activeRouteName El nombre de la ruta activa actual.
     * @param bool $forceEmptyGroups If true, groups with no items will be included.
     * @return Collection El árbol filtrado y con nodos activos marcados.
     */
    protected function applyPermissionAndActiveFilter(array|Collection $nodes, array $allowedPermissions, ?string $activeRouteName = null, bool $forceEmptyGroups = false): Collection
    {
        $filtered = collect();

        foreach ($nodes as $node) {
            $clonedNode = clone $node;

            $items = $this->applyPermissionAndActiveFilter($clonedNode->getItems(), $allowedPermissions, $activeRouteName, $forceEmptyGroups);

            // Intentar establecer la URL del grupo si tiene elementos
            if ($clonedNode->isGroup && $items->isNotEmpty()) {
                $this->setGroupUrlFromFirstChild($clonedNode, $items);
            }

            // Marcar el nodo como activo
            $nodeIsActive = ($clonedNode->getUrlName() && $clonedNode->getUrlName() === $activeRouteName) || $clonedNode->getId() === $activeRouteName;
            $itemsAreActive = $items->contains(fn($item) => $item->isActive());
            $clonedNode->setIsActive($nodeIsActive || $itemsAreActive);

            $hasPermission = $this->hasValidPermission($clonedNode, $allowedPermissions);

            // Lógica de inclusión basada en permisos, si es grupo, o si tiene ítems, o si se fuerza la inclusión de grupos vacíos
            $shouldInclude = ($hasPermission && !$clonedNode->isGroup) // Si tiene permiso y no es grupo, siempre incluir
                || ($clonedNode->isGroup && $items->isNotEmpty()) // Si es grupo y tiene items, incluir
                || ($clonedNode->isGroup && $forceEmptyGroups && $hasPermission); // Si es grupo, se fuerza y tiene permiso, incluir

            // Si es un grupo y no tiene ítems, y no se fuerza la inclusión, y no tiene permiso, omitirlo.
            // La condición `$hasPermission` para grupos vacíos con `forceEmptyGroups` es clave.
            if ($clonedNode->isGroup && $items->isEmpty() && !$forceEmptyGroups) {
                // Si no tiene permisos, o tiene permisos pero no se fuerza la inclusión de grupos vacíos,
                // y el grupo está realmente vacío, no se incluye.
                if (!$hasPermission || ($hasPermission && $items->isEmpty() && !$forceEmptyGroups)) {
                    continue;
                }
            }


            // Si el nodo no debe incluirse y no es un grupo, o si es un grupo vacío y no se fuerza, continuar
            if (!$shouldInclude && !$clonedNode->isGroup) {
                continue;
            }

            // Reconstruir la colección de items del nodo clonado
            $clonedNode->setItems(new Collection());
            foreach ($items as $item) {
                $clonedNode->addItem($item);
            }
            $filtered->push($clonedNode);
        }
        return $filtered;
    }

    protected function setGroupUrlFromFirstChild($node, Collection $items): void
    {
        $firstItemWithUrl = $items->first(fn($item) => $item->getUrl() !== null);
        if ($firstItemWithUrl) {
            $node->setUrl($firstItemWithUrl->getUrl());
            $node->setUrlName($firstItemWithUrl->getUrlName());
        }
    }

    protected function hasValidPermission($node, array $allowedPermissions): bool
    {
        // Para grupos, el permiso es solo un requisito adicional si se define.
        // Para items, el permiso es fundamental.
        if ($node->accessPermission === null) {
            return true; // No requiere permiso, siempre es válido
        }
        return in_array($node->accessPermission, $allowedPermissions);
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
        $sourceTree = $this->get(); // Obtiene el árbol completo filtrado y cacheado

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
     * @param string|null $activeRouteName The name of the active route. If null, attempts to get from Laravel's request.
     * @return Fp\RoutingKit\Contracts\FpEntityInterface|null The root entity of the active branch, or null if not found.
     */
    public function getActiveBranch(?string $activeRouteName = null): ?FpEntityInterface
    {
        $activeRouteName = $activeRouteName ?? request()->route()?->getName();
        if (!$activeRouteName) {
            return null;
        }

        $sourceTree = $this->get(); // Obtiene el árbol completo filtrado y cacheado

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
     * **NUEVO:** Obtiene las migas de pan para la ruta activa actual.
     * Aplica los filtros configurados en la cadena de consulta actual.
     *
     * @param string|null $activeRouteName El nombre de la ruta activa. Si es null, se intenta obtener de la solicitud de Laravel.
     * @return Collection Una colección de entidades que representan las migas de pan para la ruta activa.
     */
    public function getBreadcrumbsForCurrentRoute(?string $activeRouteName = null): Collection
    {
        $activeRouteName = $activeRouteName ?? request()->route()?->getName();

        if (!$activeRouteName) {
            return collect(); // Si no hay ruta activa, no hay migas de pan.
        }

        // Obtiene el árbol completo ya filtrado por la cadena de consulta actual
        $sourceTree = $this->get();
        $flattenedSource = $this->flattenTree($sourceTree);

        // **CORRECCIÓN:** Asegurarse de que la entidad obtenida sea del tipo correcto FpEntityInterface.
        // Si no se encuentra, o no es del tipo esperado, se devuelve una colección vacía.
        $activeEntity = $flattenedSource->get($activeRouteName);

        if (!$activeEntity instanceof FpEntityInterface) {
            return collect(); // No se encontró una entidad activa válida o no es del tipo correcto.
        }

        // Llama al método getBreadcrumbs con la entidad FpEntityInterface.
        return $this->getBreadcrumbs($activeEntity);
    }

    /**
     * Helper to flatten a tree into a single-level collection indexed by ID.
     *
     * @param Collection $tree
     * @return Collection
     */
    protected function flattenTree(Collection $tree): Collection
    {
        $flattened = collect();
        foreach ($tree as $node) {
            $flattened->put($node->getId(), $node);
            if ($node->getItems()->isNotEmpty()) {
                $flattened = $flattened->merge($this->flattenTree($node->getItems()));
            }
        }
        return $flattened;
    }

    // Aquí se asume que existe un método `getContextKeys()` y `getContextInstance()`
    // en la clase que usa este trait o en la clase padre `BaseOrchestrator`.
    // Ejemplo (añadir si no existen):
    // abstract protected function getContextKeys(): array;
    // abstract protected function getContextInstance(string $contextKey): FpEntityInterface; // O la interfaz de tu contexto.
}
