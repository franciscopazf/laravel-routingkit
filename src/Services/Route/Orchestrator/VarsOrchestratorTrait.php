<?php

namespace  Fp\FullRoute\Services\Route\Orchestrator;

use App\Models\User;
use Fp\FullRoute\Contracts\FpEntityInterface;
use Illuminate\Support\Collection;

trait VarsOrchestratorTrait
{
    public ?Collection $treeAllEntitys  = null;
    public ?Collection $flattenedAllEntities = null;
    public ?Collection $allFlattenedWhitChilds = null;
    public ?Collection $breadcrumbs = null;

    public ?Collection $filteredTreeAllEntities = null;
    public ?Collection $filteredTreeAllEntitiesWithPermissions = null;

    protected ?FpEntityInterface $activeBranch = null;

    public array $breadcrumbActive = [];

    protected array $excludedContextKeys = []; // Las claves de contexto a excluir
    protected ?Collection $allExcludingContexts = null;
    protected ?Collection $allExclusedContexts = null;


    public function all(): Collection
    {
        if ($this->treeAllEntitys === null) {
            $this->treeAllEntitys = $this->getAllFlattenedRoutesGlobal();
            $this->treeAllEntitys = $this->buildTreeFromFlattened($this->treeAllEntitys);
        }

        return $this->treeAllEntitys;
    }

    public function allFlattened(): Collection
    {
        if ($this->flattenedAllEntities === null) {
            $this->flattenedAllEntities = $this->getAllFlattenedRoutesGlobal();
        }

        return $this->flattenedAllEntities;
    }

    /**
     * Establece los contextos a excluir para futuras operaciones.
     * @param string|array $contextKeys Una o varias claves de contexto a excluir.
     * @return self
     */
    public function excludeContexts(string|array $contextKeys): self
    {
        $this->excludedContextKeys = is_array($contextKeys) ? $contextKeys : [$contextKeys];
        // Invalida los cachés relacionados con exclusiones
        $this->allExcludingContexts = null;
        $this->allExclusedContexts = null;
        // Si el `entityMap` en BaseOrchestrator también necesita ser actualizado,
        // tendrías que llamar a `rebuildEntityMap()` aquí o en BaseOrchestrator.
        // Para esta implementación, asumimos que `rebuildEntityMap` ya considera `activeContextKeys`.
        // Si quieres que `all()` y `allFlattened()` también respeten exclusiones sin modificar `activeContextKeys`,
        // entonces necesitarías modificar `getAllFlattenedRoutesGlobal()` para usar `excludedContextKeys`.
        // La implementación actual asume que `excludeContexts` es para `allExcludingContexts` y `allExclusedContexts` específicamente.
        return $this;
    }

    /**
     * Obtiene todas las entidades de todos los contextos, excluyendo los contextos especificados.
     *
     * @return Collection
     */
    public function allExcludingContexts(): Collection
    {
        if ($this->allExcludingContexts === null) {
            $filteredFlattened = collect();
            foreach ($this->contexts as $key => $context) {
                if (!in_array($key, $this->excludedContextKeys)) {
                    $filteredFlattened = $filteredFlattened->merge($context->getAllFlattenedRoutes());
                }
            }
            $this->allExcludingContexts = $this->buildTreeFromFlattened($filteredFlattened);
        }
        return $this->allExcludingContexts;
    }

    /**
     * Obtiene solo las entidades de los contextos especificados como excluidos.
     *
     * @return Collection
     */
    public function allExclusedContexts(): Collection
    {
        if ($this->allExclusedContexts === null) {
            $filteredFlattened = collect();
            foreach ($this->contexts as $key => $context) {
                if (in_array($key, $this->excludedContextKeys)) {
                    $filteredFlattened = $filteredFlattened->merge($context->getAllFlattenedRoutes());
                }
            }
            $this->allExclusedContexts = $this->buildTreeFromFlattened($filteredFlattened);
        }
        return $this->allExclusedContexts;
    }


    /**
     * Obtiene una sub-rama (sub-árbol) de entidades a partir de un ID de entidad raíz.
     *
     * @param string $rootEntityId El ID de la entidad que será la raíz de la sub-rama.
     * @return Collection Una colección con la sub-rama, o vacía si no se encuentra la entidad raíz.
     */
    public function getSubBranch(string $rootEntityId): Collection
    {
        $allEntitiesWithChilds = $this->getAllFlattenedWhitChilds(); // Asegura que esté cargado

        if (!isset($allEntitiesWithChilds[$rootEntityId])) {
            return collect(); // La entidad raíz no existe
        }

        $rootEntity = clone $allEntitiesWithChilds[$rootEntityId]; // Clonar para no modificar el original
        $rootEntity->setParentId(null); // La raíz del sub-árbol no tiene padre en este contexto
        $rootEntity->setChildrens(new Collection()); // Limpiar hijos para reconstruir el sub-árbol

        $subTree = collect([$rootEntity]);
        $this->buildSubTree($rootEntity, $allEntitiesWithChilds); // Construir el sub-árbol recursivamente

        return $subTree;
    }

    /**
     * Método auxiliar recursivo para construir un sub-árbol.
     * @param FpEntityInterface $currentNode El nodo actual al que se le añadirán los hijos.
     * @param Collection $allEntitiesFlattened Todas las entidades aplanadas con sus hijos.
     * @return void
     */
    protected function buildSubTree(FpEntityInterface $currentNode, Collection $allEntitiesFlattened): void
    {
        foreach ($allEntitiesFlattened as $entity) {
            if ($entity->getParentId() === $currentNode->getId()) {
                $clonedChild = clone $entity;
                $currentNode->addChild($clonedChild);
                $this->buildSubTree($clonedChild, $allEntitiesFlattened);
            }
        }
    }


    // All Routes
    public function buildTreeFromFlattened(Collection $flat): Collection
    {
        // Mapa temporal de ID a copias de entidades
        $cloned = collect();

        // Primero clonamos todas las entidades
        foreach ($flat as $entity) {
            if ($entity->getMakerMethod() === 'makeSelf') {
                $originalId = $entity->getId();
                $sourceEntity = $this->findById($entity->getInstanceRouteId()); // Asumiendo que getInstanceRouteId existe y funciona

                if (!$sourceEntity) {
                    throw new \Exception("Entidad base no encontrada para makeSelf: " . $entity->getInstanceRouteId());
                }

                $clonedEntity = clone $sourceEntity;
                $clonedEntity->setId($originalId);
                $entity = $clonedEntity;
            }
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
            // Asegúrate de que flattenedAllEntities esté cargado antes de construir el árbol
            $this->allFlattened();
            $this->buildTreeFromFlattened($this->flattenedAllEntities);
        }

        return $this->allFlattenedWhitChilds;
    }

    /**
     * Get all routes in a flattened structure across the active contexts.
     *
     * @return Collection
     */
    public function getAllFlattenedRoutesGlobal(): ?Collection
    {
        if ($this->flattenedAllEntities === null) {
            $this->flattenedAllEntities = collect();
            foreach ($this->activeContextKeys as $key) { // Usamos activeContextKeys
                if (isset($this->contexts[$key])) {
                    $this->flattenedAllEntities = $this->flattenedAllEntities->merge($this->contexts[$key]->getAllFlattenedRoutes());
                }
            }
        }
        return $this->flattenedAllEntities;
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
        else
            $id = $entity;

        $flattened = $this->getAllFlattenedRoutesGlobal();
        $byId = $flattened->keyBy(fn($entity) => $entity->getId());

        $breadcrumb = [];

        // Verifica si la entidad existe en la colección aplanada
        if (!isset($byId[$id])) {
            return collect(); // Retorna una colección vacía si la entidad no existe
        }

        $currentId = $id; // Usar una variable para el ID actual en el bucle

        $flag = false;
        while (!$flag) {
            if (!isset($byId[$currentId])) {
                // Esto puede pasar si la entidad no está en la colección aplanada (ej. si fue eliminada o nunca se cargó)
                break;
            }
            $entity = $byId[$currentId];
            array_unshift($breadcrumb, $entity); // prepend to breadcrumb
            if ($entity->getParentId() === null)
                $flag = true;
            else
                $currentId = $entity->getParentId();
        }

        return collect($breadcrumb);
    }

    /**
     * Verifica si hay una rama activa establecida.
     * @return bool
     */
    public function hasActiveBranch(): bool
    {
        return $this->activeBranch !== null;
    }

    /**
     * Verifica si hay breadcrumbs cargados para la ruta activa.
     * @return bool
     */
    public function hasBreadcrumbs(): bool
    {
        return !empty($this->breadcrumbActive);
    }

    // functions for filtering entities
    // filtered entities with roles and permissions of the current user or given roles.

    public function prepareDataForTheCurrentUser(): void
    {
        $this->getAllOfCurrenUser();
    }

    public function getBreadcrumbsActive(): Collection
    {
        if ($this->breadcrumbActive === null) {
            $this->prepareDataForTheCurrentUser();
        }
        return collect($this->breadcrumbActive);
    }


    public function getActiveBranch(): FpEntityInterface
    {
        if ($this->activeBranch === null) {
            $this->getAllOfCurrenUser();
        }

        return $this->activeBranch;
    }


    // retorna el arbol tal que el usuario actual tenga permiso de acceso o que sean publicas.
    public function getAllOfCurrenUser(): Collection
    {

        if ($this->filteredTreeAllEntities === null) {

            // Obtener el usuario actual
            $user = auth()->user();

            // Si no hay usuario autenticado, lanzar una excepción
            if (!$user) {
                throw new \RuntimeException("No hay usuario autenticado.");
            }

            $this->filteredTreeAllEntities = $this->getAllOfUser($user);
        }

        // Si ya se ha cargado el árbol de rutas, simplemente retornar las rutas filtradas
        return $this->filteredTreeAllEntities;
    }


    public function getAllOfUser(User $user): Collection
    {
        // Obtener roles del usuario
        $allowedRoles = $user->roles;

        // Si el usuario no tiene roles, retornar una colección vacía
        if ($allowedRoles->isEmpty()) {
            return collect();
        }
        // Obtener todas las rutas filtradas por los roles del usuario
        return $this->getAllWithRoles($allowedRoles);
    }

    public function getAllWithRoles(array|Collection $allowedRoles): Collection
    {
        if (is_array($allowedRoles)) {
            $allowedRoles = collect($allowedRoles);
        } elseif (!$allowedRoles instanceof Collection) {
            throw new \InvalidArgumentException('Allowed roles must be an array or a Collection.');
        }

        $permissions = $allowedRoles->flatMap(function ($role) {
            // Asegúrate de que $role->permissions es una Collection y tiene pluck('name')
            return $role->permissions->pluck('name');
        })->unique()->values()->all();

        return $this->getAllWithGroupsAndPermissions($permissions);
    }

    /**
     * Retorna el árbol de entidades filtrado por un conjunto de permisos dados.
     * @param array|Collection $allowedPermissions
     * @return Collection
     */
    public function getFilteredWithPermissions(array|Collection $allowedPermissions): Collection
    {
        return $this->getAllWithGroupsAndPermissions($allowedPermissions);
    }


    public function getAllWithGroupsAndPermissions(array|Collection $allowedPermissions): Collection
    {
        // Asegurar que los permisos estén en formato de array
        if ($allowedPermissions instanceof Collection) {
            $allowedPermissions = $allowedPermissions->all();
        }

        $tree = $this->all(); // Obtiene el árbol completo de rutas (ya jerarquizado)
        return $this->filterTreeMixed($tree, $allowedPermissions);
    }



    // filtra el arbol de rutas devolviendo solamente aquellas talque coincidan con los
    // permisos pasados

    public function filterTreeMixed(array|Collection $nodes, array $allowedPermissions, ?string $activeRouteName = null): Collection
    {
        if ($activeRouteName === null) {
            $activeRouteName = request()->route()?->getName();
        }

        $filtered = collect();

        foreach ($nodes as $node) {
            // Clonar el nodo para evitar modificar el original en el árbol global
            $clonedNode = clone $node;

            $children = $this->filterTreeMixed($clonedNode->getChildrens(), $allowedPermissions, $activeRouteName);

            if ($clonedNode->isGroup && $children->isNotEmpty()) {
                $this->setGroupUrlFromFirstChild($clonedNode, $children);
            }

            $clonedNode->isActive = $this->isNodeActive($clonedNode, $activeRouteName, $children);

            if ($clonedNode->isActive) {
                $this->addToBreadcrumb(clone $clonedNode);
            }

            if ($this->hasValidPermission($clonedNode, $allowedPermissions) || $children->isNotEmpty()) {
                $clonedNode->setChildrens(new Collection()); // Reiniciar hijos del clon
                foreach ($children as $child) {
                    $clonedNode->addChild($child);
                }

                if ($clonedNode->isActive) {
                    $this->addToActiveBranch(clone $clonedNode);
                }

                $filtered->push($clonedNode);
            }
        }

        return $filtered;
    }

    protected function setGroupUrlFromFirstChild($node, Collection $children): void
    {
        $firstChild = $children->first();
        if ($firstChild) { // Asegúrate de que hay un primer hijo
            $node->setUrl($firstChild->url);
            $node->setUrlName($firstChild->urlName);
        }
    }

    protected function isNodeActive($node, ?string $activeRouteName, Collection $children): bool
    {
        if ($node->id === $activeRouteName) {
            return true;
        }

        foreach ($children as $child) {
            if ($child->isActive) {
                return true;
            }
        }

        return false;
    }

    protected function hasValidPermission($node, array $allowedPermissions): bool
    {
        return !$node->isGroup && (
            is_null($node->accessPermission) || in_array($node->accessPermission, $allowedPermissions)
        );
    }

    protected function addToBreadcrumb($node): void
    {
        $node->setChildrens(new Collection()); // Asegurarse de que no arrastre hijos
        array_unshift($this->breadcrumbActive, $node);
    }

    protected function addToActiveBranch($node): void
    {
        $this->activeBranch = clone $node;
      //  $this->activeBranch->setChildrens(new Collection()); // Asegurarse de que no arrastre hijos
    }
}