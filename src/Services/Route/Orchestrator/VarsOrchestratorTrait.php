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


    public function all(): Collection
    {
        if ($this->treeAllEntitys === null) {
            $this->treeAllEntitys = $this->getAllFlattenedRoutesGlobal();
            $this->treeAllEntitys = $this->buildTreeFromFlattened($this->treeAllEntitys);
        }

        return $this->treeAllEntitys;
    }

    public function hola()
    {
        dd('Hola desde VarsOrchestratorTrait');
    }

    public function allFlattened(): Collection
    {
        if ($this->flattenedAllEntities === null) {
            $this->flattenedAllEntities = $this->getAllFlattenedRoutesGlobal();
        }

        return $this->flattenedAllEntities;
    }

    // All Routes
    public function buildTreeFromFlattened(Collection $flat): Collection
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

    /**
     * Get all routes in a flattened structure across all contexts.
     *
     * @return Collection
     */
    public function getAllFlattenedRoutesGlobal(): ?Collection
    {
        if ($this->flattenedAllEntities === null) {
            $this->flattenedAllEntities = collect();

            foreach ($this->contexts as $context) {
                $this->flattenedAllEntities = $this->flattenedAllEntities
                    ->merge($context->getAllFlattenedRoutes());
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




    // functions for filtering entities
    // filtered entities with roles and permissions of the current user or given roles.

    public function prepareDataForTheCurrentUser(): void
    {
        // esta fun
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

            // Si no hay usuario autenticado, retornar una colección vacía
            if (!$user) {
                // lanzar una excepción o retornar una colección vacía
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
        // 
        if (is_array($allowedRoles)) {
            $allowedRoles = collect($allowedRoles);
        } elseif (!$allowedRoles instanceof Collection) {
            throw new \InvalidArgumentException('Allowed roles must be an array or a Collection.');
        }

        $permissions = $allowedRoles->flatMap(function ($role) {
            return $role->permissions->pluck('name');
        })->unique()->values()->all();

        // dd($permissions);

        return $this->getAllWithGroupsAndPermissions($permissions);
    }


    public function getAllWithGroupsAndPermissions(array|Collection $allowedPermissions): Collection
    {
        // Asegurar que los permisos estén en formato de array
        if ($allowedPermissions instanceof Collection) {
            $allowedPermissions = $allowedPermissions->all();
        }

        $tree = $this->all(); // Obtiene el árbol completo de rutas (ya jerarquizado)
        //dd($this->filterTreeMixed($tree, $allowedPermissions));
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
            $children = $this->filterTreeMixed($node->getChildrens(), $allowedPermissions, $activeRouteName);

            if ($node->isGroup && $children->isNotEmpty()) {
                $this->setGroupUrlFromFirstChild($node, $children);
            }

            $node->isActive = $this->isNodeActive($node, $activeRouteName, $children);

            if ($node->isActive) {
                $this->addToBreadcrumb(clone $node);
            }

            if ($this->hasValidPermission($node, $allowedPermissions) || $children->isNotEmpty()) {
                $node->setChildrens([]);
                foreach ($children as $child) {
                    $node->addChild($child);
                }

                if ($node->isActive) {
                    $this->addToActiveBranch(clone $node);
                }


                $filtered->push($node);
            }
        }

        return $filtered;
    }

    protected function setGroupUrlFromFirstChild($node, Collection $children): void
    {
        $firstChild = $children->first();
        $node->setUrl($firstChild->url);
        $node->setUrlName($firstChild->urlName);
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
        $node->setChildrens([]);
        array_unshift($this->breadcrumbActive, $node);
    }

    protected function addToActiveBranch($node): void
    {
        $this->activeBranch = clone $node;
    }
}
