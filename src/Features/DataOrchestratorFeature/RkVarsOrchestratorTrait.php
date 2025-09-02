<?php

namespace Rk\RoutingKit\Features\DataOrchestratorFeature;

use App\Models\User;
use Rk\RoutingKit\Contracts\RkEntityInterface;
use Illuminate\Support\Collection;
use RuntimeException;

trait RkVarsOrchestratorTrait
{
    // //--- Internal Caches (Global & Contextual) //---
    protected ?Collection $globalTreeAllEntities = null;
    protected ?Collection $globalFlattenedAllEntities = null;
    protected Collection $contextualCachedEntities; // Key: contextKey, Value: Collection of flattened entities

    // El caché que contendrá la estructura final "híbrida" (aplanada por ID, con referencias a hijos)
    // Cada nodo aquí es el resultado final de los filtros y tiene sus 'items' enlazados a otros nodos de esta misma caché.
    protected ?Collection $filteredEntitiesCache = null;

    // //--- Current Filter State (Transient per query chain) //---
    protected ?int $currentDepthFilterLevel = null;
    protected ?string $currentUserFilterId = null;
    protected array $currentExcludedContextKeys = [];
    protected array $currentIncludedContextKeys = [];
    protected bool $forceEmptyGroups = false;

    protected ?array $permissionsForFilter = null;

    /**
     * Constructor del Orchestrator.
     * Inicializa las cachés internas.
     */
    public function __construct()
    {
        $this->contextualCachedEntities = new Collection();
        // El estado de la consulta se inicializa y reinicia a través de newQuery() y resetFilters().
    }

    /**
     * Inicia una nueva "query" en el orquestador, asegurando que los filtros temporales sean limpiados.
     * Proporciona un punto de entrada claro para iniciar cadenas de filtros.
     * @return static
     */
    public function newQuery(): static
    {
        $this->resetQueryState(); // Reinicia el estado de la consulta
        // Después de resetear el estado, asegura que los contextos incluidos estén en su estado inicial
        // (todos los contextos por defecto).
        $this->setIncludedContextKeys($this->getContextKeys());
        return $this;
    }

    /**
     * Reinicia el estado de los filtros transitorios de la consulta actual.
     * Esto se llama internamente por `newQuery()` o `resetFilters()`.
     * @return void
     */
    protected function resetQueryState(): void
    {
        $this->currentDepthFilterLevel = null;
        $this->currentUserFilterId = null;
        $this->currentExcludedContextKeys = []; // Esto se limpiará al establecer nuevos includedKeys
        $this->forceEmptyGroups = false; // Reinicia el estado de grupos vacíos
        $this->filteredEntitiesCache = null; // **CRUCIAL:** Invalida la caché del resultado final
        // $this->permissionsForFilter = [];
    }

    /**
     * Establece las claves de contexto incluidas y, si hay cambios, invalida la caché.
     * @param array $keys
     * @return void
     */
    protected function setIncludedContextKeys(array $keys): void
    {
        // Solo actualizar si realmente ha cambiado para evitar invalidar la caché innecesariamente.
        if ($this->currentIncludedContextKeys !== $keys) {
            $this->currentIncludedContextKeys = $keys;
            $this->filteredEntitiesCache = null; // Invalida la caché del resultado final
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
     * Obtiene las entidades crudas y sin filtrar de un contexto específico,
     * utilizando un caché para optimizar futuras solicitudes.
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
    public function forUser(User $user): static
    {
        // $this->currentUserFilterId = $userId;
        $this->filteredEntitiesCache = null; // Invalida la caché del resultado final
        $this->rolesForFilter = $user->roles;
        $this->filterForRoles($this->rolesForFilter);

        //  dd($this->permissionsForFilter);
        return $this;
    }

    public function filterForPermissions(array|Collection $permissions): static
    {
        if ($permissions instanceof Collection) {
            $permissions = $permissions->all();
        }
        $this->filteredEntitiesCache = null; // Invalida la caché del resultado final
        $this->permissionsForFilter = $permissions;
        return $this;
    }

    public function filterForRoles(Collection $roles): static
    {
        $this->filteredEntitiesCache = null; // Invalida la caché del resultado final
        $permissionsForFilter = $roles->flatMap(fn($role) => $role->permissions->pluck('name'))
            ->unique()->values()->all();
        $this->filterForPermissions($permissionsForFilter);
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
        $this->currentExcludedContextKeys = []; // Limpiamos las exclusiones si se cargan contextos específicos
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
        return $this;
    }

    /**
     * Reinicia todos los filtros configurados en la instancia actual del orquestador.
     * Vuelve a inicializar con todos los contextos disponibles y limpia los filtros temporales.
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
    public function prepareForUser(null|User $user = null): static
    {
        if ($user === null) {
            $user = auth()->user();
            if (!$user) {
                throw new RuntimeException("Necesito un usuario autenticado para preparar los filtros.");
            }
        }
        return $this->forUser($user);
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
     * Obtiene el árbol de entidades completo o filtrado por contextos si se han especificado,
     * aplicando **todos los filtros configurados** (usuario, profundidad, contextos incluidos/excluidos, grupos vacíos).
     * Este es el método central para obtener el resultado final de una query.
     *
     * Si `currentIncludedContextKeys` está vacío, asume que se deben cargar todos los contextos disponibles.
     *
     * @return Collection El árbol de entidades anidado y filtrado.
     */
    public function get(): Collection
    {
        // Si el caché híbrido aún no se ha construido, primero lo construimos.
        if ($this->filteredEntitiesCache === null) {
            // Si currentIncludedContextKeys está vacío, asumimos que se deben cargar todos los contextos disponibles.
            if (empty($this->currentIncludedContextKeys) && !empty($this->getContextKeys())) {
                $this->setIncludedContextKeys($this->getContextKeys());
            }

            // Paso 1: Cargar todas las entidades aplanadas de los contextos relevantes (colección de objetos originales).
            $flattenedEntitiesRaw = $this->getFlattenedEntitiesByActiveContexts();

            // Paso 2: Construir el árbol inicial de **referencias** a las entidades raw.
            $treeOfReferences = $this->buildTreeFromFlattened($flattenedEntitiesRaw);

            // Paso 3: Aplicar filtros de usuario/permisos, marcar nodos activos y decidir grupos vacíos.
            $processedTree = collect(); // Variable temporal para el resultado de los filtros
            // dd($this->permissionsForFilter);
            if ($this->permissionsForFilter !== null) {
                $permissions = $this->permissionsForFilter;
                $processedTree = $this->applyPermissionAndActiveFilter($treeOfReferences, $permissions, request()->route()?->getName(), $this->forceEmptyGroups);
            } else {
                $processedTree = $this->markActiveNodesAndSetGroupUrls($treeOfReferences, request()->route()?->getName(), $this->forceEmptyGroups);
            }


            // Paso 4: Aplicar filtro de profundidad.
            if ($this->currentDepthFilterLevel !== null) {
                $processedTree = $this->filterTreeByDepth($processedTree, 0, $this->currentDepthFilterLevel);
            }

            // Paso 5: Aplanar el árbol final procesado para crear el CACHÉ "HÍBRIDO".
            // Los nodos en $processedTree ya son CLONES que han pasado por todos los filtros.
            // Sus `items` ya apuntan a otros CLONES dentro de ese mismo árbol filtrado.
            // flattenTreeForCache ahora simplemente los indexa por ID.
            $this->filteredEntitiesCache = $this->flattenTreeForCache($processedTree);
        }

        // Si ya tenemos el caché híbrido, simplemente lo usamos para reconstruir el árbol anidado para la salida.
        // Aquí es CRUCIAL CLONAR los objetos del caché para la salida, para no modificar el caché original.
        return $this->rebuildNestedTreeFromFlattened($this->filteredEntitiesCache);
    }

    /**
     * Reconstruye un árbol anidado a partir de una colección aplanada de entidades.
     * Asume que las entidades en $flattened tienen sus IDs y parent IDs correctamente configurados
     * y que sus colecciones de 'items' ya están enlazadas correctamente como clones o referencias.
     *
     * **CLONA cada nodo del caché para la salida, manteniendo el caché inmutable.**
     *
     * @param Collection $flattened Una colección aplanada de entidades (generalmente el caché híbrido).
     * @return Collection El árbol anidado reconstruido.
     */
    protected function rebuildNestedTreeFromFlattened(Collection $flattened): Collection
    {
        $tree = new Collection();
        $nodesById = new Collection(); // Para facilitar el acceso a los nodos por ID durante la reconstrucción

        // Paso 1: Clonar cada nodo del caché aplanado y limpiar sus `items`
        // Esto es CRÍTICO para que la reconstrucción del árbol no modifique los objetos originales en el caché.
        foreach ($flattened as $id => $entity) {
            $clonedEntity = clone $entity;
            // IMPORTANTE: Limpiar los items del clon para reconstruirlos con los *otros clones*
            // dentro de este proceso de reconstrucción. Los items del original en el caché
            // DEBEN permanecer intactos.
            $clonedEntity->setItems(new Collection());
            $nodesById->put($id, $clonedEntity);
        }

        // Paso 2: Reconstruir las relaciones padre-hijo usando los CLONES
        foreach ($nodesById as $id => $entity) {
            $parentId = $entity->getParentId();
            if ($parentId !== null && $nodesById->has($parentId)) {
                $parent = $nodesById->get($parentId);
                $parent->addItem($entity); // Añade el hijo (clonado) al padre (clonado).
            } else {
                // Si no tiene padre o el padre no está en la colección procesada, es una raíz.
                $tree->push($entity);
            }
        }
        return $tree;
    }


    /**
     * Obtiene el árbol de entidades completo o filtrado por contextos si se han especificado.
     * Este método ahora simplemente llama a `get()` para aprovechar la lógica de filtrado y la caché.
     *
     * @return Collection El árbol de entidades resultante de los contextos activos y filtros.
     */
    public function all(): Collection
    {
        // El método all() ahora es un alias de get(), lo que asegura que
        // siempre se apliquen todos los filtros configurados y se use la caché.
        return $this->get();
    }

    /**
     * Recursively marks active nodes and sets group URLs based on their child items,
     * considering whether empty groups should be included.
     * **Clones nodes** to ensure the cached tree reflects the processed state without modifying raw entities.
     *
     * @param Collection|array $nodes The nodes of the tree to process (can be references).
     * @param string|null $activeRouteName The name of the active route.
     * @param bool $forceEmptyGroups If true, groups with no items will be included.
     * @return Collection The processed tree with active nodes marked and group URLs set.
     */
    protected function markActiveNodesAndSetGroupUrls(array|Collection $nodes, ?string $activeRouteName = null, bool $forceEmptyGroups = false): Collection
    {
        $processed = collect();

        foreach ($nodes as $node) {
            // Primero procesar hijos recursivamente. Los hijos devueltos ya serán clones procesados.
            $items = $this->markActiveNodesAndSetGroupUrls($node->getItems(), $activeRouteName, $forceEmptyGroups);

            // Determinar si el nodo actual debe ser incluido basado en si es un grupo vacío y la configuración.
            if ($node->isGroup && $items->isEmpty() && !$forceEmptyGroups) {
                continue; // Omitir este grupo vacío si no se fuerza su inclusión
            }

            // Clonar el nodo actual SÓLO si va a ser incluido, y luego modificar el clon.
            // Este clon es el que potencialmente irá al filteredEntitiesCache.
            $clonedNode = clone $node;
            $clonedNode->setItems(new Collection()); // Limpiar ítems para reconstruirlos con los hijos procesados
            $clonedNode->setAcuntBageInt(0); // Resetear acuntBageInt para el nodo clonado

            // Si es un grupo y tiene items procesados, intenta establecer su URL
            if ($clonedNode->isGroup && $items->isNotEmpty()) {
                $this->setGroupUrlFromFirstChild($clonedNode, $items);
            }

            // Marcar el nodo como activo
            $nodeIsActive = ($clonedNode->getUrlName() && $clonedNode->getUrlName() === $activeRouteName) || $clonedNode->getId() === $activeRouteName;
            $itemsAreActive = $items->contains(fn($item) => $item->isActive());
            $clonedNode->setIsActive($nodeIsActive || $itemsAreActive);

            // Reconstruir la colección de items del nodo clonado (referencias a los clones de los hijos)
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
     * This method will use `currentIncludedContextKeys` to get raw entities from contexts.
     *
     * @return Collection A flattened collection of raw entities based on active contexts.
     */
    public function allFlattened(): Collection
    {
        // Este método se encarga de cargar entidades según los filtros de contexto
        // y es llamado por `get()` para la construcción del árbol inicial.
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
     * **Ahora utiliza directamente el `filteredEntitiesCache` para una recuperación rápida.**
     *
     * @param string $rootEntityId El ID de la entidad que será la raíz de la sub-rama.
     * @return Collection Una colección con la sub-rama, o vacía si no se encuentra la entidad raíz.
     */
    public function getSubBranch(string $rootEntityId): Collection
    {
        // Asegura que el caché esté construido con todos los filtros aplicados.
        // `get()` ya se encarga de construir y poblar `filteredEntitiesCache` si es null.
        $this->get();

        // Buscar directamente la entidad raíz en el caché aplanado.
        if (!$this->filteredEntitiesCache->has($rootEntityId)) {
            return collect();
        }

        // La entidad ya está en su estado final filtrado y sus `items` ya son referencias
        // a otros nodos dentro del mismo `filteredEntitiesCache`.
        $rootEntityFromCache = $this->filteredEntitiesCache->get($rootEntityId);

        // Clonar la entidad raíz para la salida. Esto asegura que cualquier manipulación
        // posterior de esta sub-rama no afecte el `filteredEntitiesCache`.
        $clonedRootEntity = clone $rootEntityFromCache;
        $clonedRootEntity->setParentId(null); // La nueva raíz no tiene padre en su nueva sub-estructura.

        // IMPORTANTE: Los `items` de $clonedRootEntity ya contienen las referencias
        // a los clones de sus hijos del $filteredEntitiesCache, ¡así que la sub-rama ya está completa!
        // No necesitamos reconstruir recursivamente aquí, ya está pre-construida en el caché.

        return collect([$clonedRootEntity]);
    }

    /**
     * Obtiene múltiples subramas a partir de un arreglo de IDs de entidades raíz.
     * Aplica todos los filtros configurados en la instancia actual del orquestador.
     *
     * **Ahora utiliza directamente el `filteredEntitiesCache` para una recuperación rápida.**
     *
     * @param array $rootEntityIds Un arreglo de IDs de entidades que serán las raíces de las subramas.
     * @return Collection Una colección de colecciones, donde cada sub-colección es una subrama.
     */
    public function getSubBranches(array $rootEntityIds): Collection
    {
        $allSubBranches = collect();
        // Asegura que el caché esté construido con todos los filtros aplicados.
        $this->get();

        foreach ($rootEntityIds as $rootId) {
            if (!$this->filteredEntitiesCache->has($rootId)) {
                continue; // Saltar si la raíz no existe en el árbol ya filtrado.
            }

            // Obtener la entidad directamente del caché.
            $rootEntityFromCache = $this->filteredEntitiesCache->get($rootId);

            // Clonar la entidad raíz para la salida.
            $clonedRootEntity = clone $rootEntityFromCache;
            $clonedRootEntity->setParentId(null); // La nueva raíz no tiene padre en su nueva sub-estructura.

            // Sus ítems ya están configurados correctamente en la entidad original del caché.
            $allSubBranches->push($clonedRootEntity);
        }

        return $allSubBranches;
    }


    //---

    ### **Core Internal Tree Operations**

    /**
     * Gets the raw, unfiltered global tree from ALL available contexts.
     * Ignora cualquier filtro de inclusión/exclusión de contexto aplicado.
     * Se utiliza para operaciones que requieren la totalidad de las rutas (pre-filtrado).
     * @return Collection El árbol de entidades completo y sin filtrar.
     */
    protected function getRawGlobalTree(): Collection
    {
        // Carga perezosa para el árbol global completo
        if ($this->globalTreeAllEntities === null) {
            $flattened = $this->getRawGlobalFlattened();
            // buildTreeFromFlattened ahora trabaja con referencias para el árbol inicial
            $this->globalTreeAllEntities = $this->buildTreeFromFlattened($flattened);
        }
        return $this->globalTreeAllEntities;
    }

    /**
     * Gets the raw, unfiltered global flattened list by loading ALL contexts.
     * Ignora cualquier filtro de inclusión/exclusión de contexto aplicado.
     * @return Collection La colección aplanada de todas las entidades crudas.
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
     * Construye un árbol a partir de una colección aplanada de entidades.
     * **Manipula referencias a las entidades originales siempre que sea posible.**
     * Las entidades 'makeSelf' son clonadas si es necesario reasignarles un ID.
     * Los `acuntBageInt` e `items` de las entidades se re-inicializan al ser procesadas para el árbol.
     *
     * @param Collection $flat Una colección aplanada de RkEntityInterface (entidades raw).
     * @return Collection El árbol construido con referencias a las entidades originales o clones necesarios.
     */
    public function buildTreeFromFlattened(Collection $flat): Collection
    {
        $entitiesById = collect();

        foreach ($flat as $entity) {
            // No clonamos la entidad aquí inicialmente, trabajamos con la referencia.
            // Clonamos solo si es un 'makeSelf' y necesita un nuevo ID,
            // o si es la forma en que queremos que los objetos en este árbol inicial comiencen.
            // Para la máxima eficiencia de referencias, no clonamos aquí.
            $currentEntity = $entity;

            // Resetear propiedades para la construcción del árbol inicial
            $currentEntity->setAcuntBageInt(0);
            $currentEntity->setItems(new Collection()); // Asegurar que los ítems estén vacíos para la reconstrucción

            if ($currentEntity->getMakerMethod() === 'makeSelf') {
                $originalId = $currentEntity->getId();
                // Obtener la entidad fuente del aplanado global (asumiendo que tiene todas las entidades)
                $sourceEntity = $this->getRawGlobalFlattened()->get($currentEntity->getInstanceRouteId());

                if (!$sourceEntity) {
                    continue; // Saltar si la entidad fuente no existe
                }

                // Si es makeSelf, clonamos la entidad fuente para que actúe como la entidad original,
                // pero con el ID y el método makeSelf del proxy.
                $clonedSource = clone $sourceEntity;
                $clonedSource->acuntBageInt = 0; // Resetear para makeSelf
                $clonedSource->setId($originalId);
                $clonedSource->setMakerMethod('makeSelf'); // Mantener el método original de makeSelf
                $currentEntity = $clonedSource; // Usar esta entidad clonada como la que se añade al mapa
                $currentEntity->setItems(new Collection()); // Limpiar ítems para la reconstrucción del árbol
            }
            $entitiesById->put($currentEntity->getId(), $currentEntity);
        }

        $tree = collect();

        foreach ($entitiesById as $id => $entity) {
            $parentId = $entity->getParentId();
            if ($parentId !== null && $entitiesById->has($parentId)) {
                $parent = $entitiesById->get($parentId);
                $parent->addItem($entity); // Añadir referencia al hijo
            } else {
                $tree->push($entity); // Añadir referencia a la raíz
            }
        }
        return $tree;
    }

    /**
     * Ayudante recursivo para construir un sub-árbol a partir de una colección aplanada.
     * **No clona los nodos hijos** al agregarlos; asume que los nodos en `$allEntitiesFlattened`
     * ya son las entidades finales (referencias o clones si fueron filtrados y procesados).
     *
     * @param RkEntityInterface $currentNode El nodo actual para el que construir ítems (referencia).
     * @param Collection $allEntitiesFlattened Todas las entidades en forma aplanada (del árbol fuente ya filtrado y procesado).
     */
    protected function buildSubTreeRecursive(RkEntityInterface $currentNode, Collection $allEntitiesFlattened): void
    {
        // Este método ya no es central para la reconstrucción de sub-ramas a partir de filteredEntitiesCache
        // porque filteredEntitiesCache ya tiene los `items` enlazados.
        // Se mantiene para compatibilidad o si se usa en otro contexto que construye árboles.
        // Ya no reseteamos acuntBageInt aquí; se asume que esto se hizo en el proceso de filtrado.
        // Los items de $currentNode ya deben estar limpios si se clonó la raíz de la sub-rama.

        foreach ($allEntitiesFlattened as $entity) {
            // Solo añadir si es hijo directo del nodo actual y no es el mismo nodo (para evitar recursión infinita con makeSelf si el ID es el mismo)
            if ($entity->getParentId() === $currentNode->getId() && $entity->getId() !== $currentNode->getId()) {
                // Añadir la referencia al hijo directamente.
                $currentNode->addItem($entity);
                // Llamar recursivamente con la referencia al hijo.
                $this->buildSubTreeRecursive($entity, $allEntitiesFlattened);
            }
        }
    }

    /**
     * Filters a tree by maximum depth.
     * **Clones nodes** that are within the allowed depth to ensure the cached tree is independent.
     *
     * @param Collection|array $nodes The nodes of the tree to filter.
     * @param int $currentLevel The current level (starts at 0).
     * @param int $maxDepth The maximum allowed depth.
     * @return Collection The depth-filtered tree with cloned nodes.
     */
    protected function filterTreeByDepth(Collection|array $nodes, int $currentLevel, int $maxDepth): Collection
    {
        $filteredNodes = new Collection();

        foreach ($nodes as $node) {
            if ($currentLevel <= $maxDepth) {
                // Clonar el nodo solo si está dentro de la profundidad permitida.
                // Este clon es el que potencialmente irá al filteredEntitiesCache.
                $clonedNode = clone $node;
                $clonedNode->setItems(new Collection()); // Resetear ítems para reconstruirlos con los hijos filtrados
                $clonedNode->setAcuntBageInt(0); // Resetear acuntBageInt para el nodo clonado

                if ($currentLevel < $maxDepth) { // Si aún hay profundidad para explorar
                    $filteredItems = $this->filterTreeByDepth($node->getItems(), $currentLevel + 1, $maxDepth);
                    foreach ($filteredItems as $item) {
                        $clonedNode->addItem($item); // Añadir referencia al clon del hijo
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
     * **Clones nodes** that pass the permission/group filter to create the final cached tree.
     *
     * @param Collection|array $nodes The nodes of the tree to filter (can be references).
     * @param array $allowedPermissions Permisos permitidos para el usuario actual.
     * @param string|null $activeRouteName El nombre de la ruta activa actual.
     * @param bool $forceEmptyGroups If true, groups with no items will be included.
     * @return Collection El árbol filtrado y con nodos activos marcados (contiene clones).
     */
    protected function applyPermissionAndActiveFilter(array|Collection $nodes, array $allowedPermissions, ?string $activeRouteName = null, bool $forceEmptyGroups = false): Collection
    {
        // dd(123);
        $filtered = collect();

        foreach ($nodes as $node) {
            // Paso 1: Procesar hijos recursivamente. Los hijos devueltos ya serán clones filtrados.
            $items = $this->applyPermissionAndActiveFilter($node->getItems(), $allowedPermissions, $activeRouteName, $forceEmptyGroups);

            // Paso 2: Evaluar si el nodo actual debe ser incluido.
            $hasPermission = $this->hasValidPermission($node, $allowedPermissions);
            $shouldInclude = false;

            if ($node->isGroup) {
                // Si es un grupo:
                // Se incluye si tiene hijos (que ya pasaron los filtros)
                // O si se fuerza la inclusión de grupos vacíos Y el grupo tiene permiso (si el permiso es un requisito para el grupo)
                $shouldInclude = $items->isNotEmpty() || ($forceEmptyGroups && $hasPermission);
            } else {
                // Si es un ítem (no grupo), se incluye si tiene permiso.
                $shouldInclude = $hasPermission;
            }

            // Si el nodo no debe incluirse, se salta.
            if (!$shouldInclude) {
                continue;
            }

            // Paso 3: Clonar el nodo si va a ser incluido y reconstruir sus ítems con los hijos filtrados.
            // Este clon es el que potencialmente irá al filteredEntitiesCache.
            $clonedNode = clone $node;
            $clonedNode->setItems(new Collection()); // Limpiar ítems para reconstruir con los procesados
            $clonedNode->setAcuntBageInt(0); // Resetear acuntBageInt para el nodo clonado

            // Si es un grupo y tiene ítems procesados, intenta establecer su URL
            if ($clonedNode->isGroup && $items->isNotEmpty()) {
                $this->setGroupUrlFromFirstChild($clonedNode, $items);
            }

            // Marcar el nodo como activo
            $nodeIsActive = ($clonedNode->getUrlName() && $clonedNode->getUrlName() === $activeRouteName) || $clonedNode->getId() === $activeRouteName;
            $itemsAreActive = $items->contains(fn($item) => $item->isActive());
            $clonedNode->setIsActive($nodeIsActive || $itemsAreActive);

            // Reconstruir la colección de items del nodo clonado (referencias a los clones de los hijos)
            foreach ($items as $item) {
                $clonedNode->addItem($item);
            }
            $filtered->push($clonedNode);
        }
        return $filtered;
    }

    /**
     * Sets the URL and URL name of a group node from its first child that has a URL.
     *
     * @param RkEntityInterface $node The group node to update.
     * @param Collection $items The child items of the group.
     * @return void
     */
    protected function setGroupUrlFromFirstChild($node, Collection $items): void
    {
        $firstItemWithUrl = $items->first(fn($item) => $item->getUrl() !== null);
        if ($firstItemWithUrl) {
            $node->setUrl($firstItemWithUrl->getUrl());
            $node->setUrlName($firstItemWithUrl->getUrlName());
        }
    }

    /**
     * Checks if a node has a valid permission for the current user.
     *
     * @param RkEntityInterface $node The entity node to check.
     * @param array $allowedPermissions An array of permissions allowed for the current user.
     * @return bool True if the node requires no permission or has a matching permission.
     */
    protected function hasValidPermission($node, array $allowedPermissions): bool
    {
        // Si no requiere permiso, siempre es válido
        if ($node->accessPermission === null) {
            return true;
        }
        // Si requiere permiso, verificar si está en la lista de permisos permitidos
        return in_array($node->accessPermission, $allowedPermissions);
    }

    //---

    ### **Unified Breadcrumbs and Active Branch Logic**

    /**
     * Gets the breadcrumbs for a specific entity from the **cached filtered tree**.
     * Optimizada para trabajar con la estructura aplanada del caché.
     *
     * @param RkEntityInterface $entity The entity for which to get breadcrumbs.
     * @return Collection A collection of entities representing the breadcrumbs.
     */
    public function getBreadcrumbs(RkEntityInterface $entity): Collection
    {
        // Asegura que el caché esté construido con todos los filtros aplicados.
        $this->get();

        $breadcrumbs = new Collection();
        $currentId = $entity->getId(); // Usar el ID de la entidad pasada

        // Recorrer la cadena de padres usando el caché aplanado.
        while ($currentId !== null && $this->filteredEntitiesCache->has($currentId)) {
            // Los elementos del caché ya son los clones finales y correctos.
            $currentEntity = $this->filteredEntitiesCache->get($currentId);
            $breadcrumbs->prepend($currentEntity); // Añadir al principio para mantener el orden correcto
            $currentId = $currentEntity->getParentId();
        }

        return $breadcrumbs;
    }

    /**
     * Gets the active branch (the current entity and its active ancestors/descendants)
     * from the **cached filtered tree**.
     *
     * @param string|null $activeRouteName The name of the active route. If null, attempts to get from Laravel's request.
     * @return RkEntityInterface|null The root entity of the active branch, or null if not found.
     */
    public function getActiveBranch(?string $activeRouteName = null): ?RkEntityInterface
    {
        $activeRouteName = $activeRouteName ?? request()->route()?->getName();
        if (!$activeRouteName) {
            return null;
        }

        // Asegura que el caché esté construido con todos los filtros aplicados.
        $this->get();

        // Buscar el nodo activo directamente en la colección aplanada del árbol filtrado.
        if (!$this->filteredEntitiesCache->has($activeRouteName)) {
            return null;
        }

        $activeNode = $this->filteredEntitiesCache->get($activeRouteName);

        // Encontrar el ancestro activo más alto que sigue siendo parte de la rama activa.
        // Se recorren los padres a través de las referencias en el caché.
        $rootOfActiveBranch = $activeNode;
        $current = $activeNode;

        while ($current->getParentId() !== null) {
            $parent = $this->filteredEntitiesCache->get($current->getParentId());
            // Se asume que isActive ya ha sido establecido correctamente durante la construcción del caché.
            if ($parent && $parent->isActive()) {
                $rootOfActiveBranch = $parent;
                $current = $parent;
            } else {
                break;
            }
        }
        // Clonar la raíz de la rama activa para la salida, manteniendo el caché inmutable.
        return $rootOfActiveBranch ? clone $rootOfActiveBranch : null;
    }

    /**
     * Obtiene las migas de pan para la ruta activa actual.
     * Aplica los filtros configurados en la cadena de consulta actual, utilizando el caché.
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

        // Asegura que el caché esté construido con todos los filtros aplicados.
        $this->get();

        $activeEntity = $this->filteredEntitiesCache->get($activeRouteName);

        if (!$activeEntity instanceof RkEntityInterface) {
            return collect(); // No se encontró una entidad activa válida o no es del tipo correcto.
        }

        // Llama al método getBreadcrumbs con la entidad RkEntityInterface.
        return $this->getBreadcrumbs($activeEntity);
    }

    /**
     * Helper para aplanar un árbol en una colección de un solo nivel indexada por ID.
     * Este método **almacena los nodos tal cual**, asumiendo que ya son los clones finales
     * y filtrados que conformarán el `filteredEntitiesCache` (el "híbrido").
     *
     * @param Collection $tree El árbol a aplanar (debería contener clones ya procesados).
     * @return Collection Una colección aplanada de entidades, indexada por su ID.
     */
    protected function flattenTreeForCache(Collection $tree): Collection
    {
        $flattened = collect();
        foreach ($tree as $node) {
            // Almacena el nodo tal cual (ya debería ser un clon si viene de los filtros).
            $flattened->put($node->getId(), $node);
            if ($node->getItems()->isNotEmpty()) {
                // Recursivamente aplanar los hijos y fusionar.
                // Los hijos ya están enlazados como referencias a otros nodos clonados.
                $flattened = $flattened->merge($this->flattenTreeForCache($node->getItems()));
            }
        }
        return $flattened;
    }


    /**
     * Obtiene el nodo padre de una entidad dada por su ID (o nombre de ruta) del caché.
     *
     * @param string $entityId El ID de la entidad (hija) de la cual se quiere obtener el padre.
     * @return RkEntityInterface|null El nodo padre, o null si no se encuentra o no tiene padre en el árbol filtrado.
     */
    public function getParentNode(string $entityId): ?RkEntityInterface
    {
        // Asegura que el caché esté construido con todos los filtros aplicados.
        $this->get();

        // Si la entidad no existe en el árbol filtrado, no podemos encontrar su padre.
        if (!$this->filteredEntitiesCache->has($entityId)) {
            return null;
        }

        $childEntity = $this->filteredEntitiesCache->get($entityId);
        $parentId = $childEntity->getParentId();

        // Si la entidad no tiene padre o el padre no está en el árbol filtrado, devuelve null.
        if ($parentId === null || !$this->filteredEntitiesCache->has($parentId)) {
            return null;
        }

        // Devolvemos el padre del caché. Se recomienda clonarlo si se va a manipular.
        return clone $this->filteredEntitiesCache->get($parentId);
    }

    /**
     * Obtiene el nodo padre del nodo activo actual, incluyendo todos sus hijos filtrados.
     * Esto te dará el padre del nodo activo, con su sub-árbol completo (ya filtrado).
     *
     * @param string|null $activeRouteName El nombre de la ruta activa. Si es null, intenta obtenerlo de Laravel's request.
     * @return RkEntityInterface|null El nodo padre del activo actual con sus hijos, o null si no se encuentra.
     */
    public function getActiveNodeParentWithChildren(?string $activeRouteName = null): ?RkEntityInterface
    {
        $activeRouteName = $activeRouteName ?? request()->route()?->getName();
        if (!$activeRouteName) {
            return null;
        }

        // Obtener el nodo padre usando la lógica de getParentNode (que usa el árbol cacheado).
        $parentNode = $this->getParentNode($activeRouteName);

        if ($parentNode === null) {
            return null;
        }

        // El parentNode que se obtiene de getParentNode ya es un clon del caché y tiene sus
        // ítems correctamente configurados y filtrados. No necesitamos llamar a getSubBranch.
        return $parentNode;
    }


    /**
     * Obtiene la colección aplanada de entidades filtradas (el "árbol híbrido" o caché).
     * Este método debería ser llamado solo DESPUÉS de que la lógica de `get()`
     * haya poblado la caché.
     *
     * @return Collection La colección aplanada de entidades filtradas, indexada por ID.
     */
    public function getFilteredEntitiesCache(): Collection
    {
        // Asegúrate de que la caché esté construida.
        // La llamada a $this->get() dentro de los métodos del Orchestrator
        // que usan esta caché (como getSubBranch, getParentNode, etc.)
        // es lo que garantiza que filteredEntitiesCache ya se haya poblado.
        // Si llamas a este método directamente sin antes haber ejecutado un `get()` o similar,
        // podría devolver una colección vacía o null si la caché no se ha construido aún.
        if ($this->filteredEntitiesCache === null) {
            $this->get(); // Fuerza la construcción de la caché si aún no se ha hecho.
        }
        return $this->filteredEntitiesCache ?? new Collection();
    }
}
