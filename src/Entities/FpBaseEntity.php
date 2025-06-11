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
     * @var Collection|array
     */
    //public array|Collection $childrens = [];

    public ?string $accesPermission = null;

    public function __construct(string $id, ?string $makerMethod = "make")
    {
        $this->id = $id;
        $this->makerMethod = $makerMethod;
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
     * Get the context of the entity.
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
    /**
     * Save permanently the entity, optionally setting a parent.
     *
     * @param string|FpEntityInterface|null $parent
     * @return static
     */
    public function save(string|FpEntityInterface|null $parent = null): static
    {
        //dd($parent);
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
        return static::getOrchestrator()
            ->findByParamName($paramName, $value);
    }

    /**
     * get all entities.
     * @return Collection
     */
    public static function all(): Collection
    {
        //  dd('all');
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
        return static::getOrchestrator()
            ->getAllByFile($filePath);
    }


    /**
     * Get all entities grouped by file, flattened.
     *
     * @return Collection
     */
    public static function getAllsByFileFlattened(): Collection
    {
        return static::getOrchestrator()
            ->getAllsByFileFlattened();
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
        return static::getOrchestrator()
            ->allInTree();
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
     * @param string $id
     * @return bool
     */
    public function isChild(string|FpEntityInterface $entity): bool
    {
        return static::getOrchestrator()
            ->isChild($this, $entity);
    }

    /**
     * returns the parent entity of the current entity.
     *
     * @param string $id
     * @return bool
     */
    public function parent(string|FpEntityInterface $parent): FpEntityInterface
    {
        return static::getOrchestrator()
            ->parent($this, $this->parent);
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
     * @return FpEntityInterface
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
     * @return static
     */
    public function addChild(FpEntityInterface $child): static
    {
        $child->setParentId($this->getId());
        $child->setLevel($this->getLevel() + 1);
        $this->childrens[] = $child;
        return $this;
    }


    // Context Methods


    public static function getContext(): array
    {
        return static::getOrchestrator()
            ->getContext();
    }

    public static function rewriteAllContext(): void
    {
        static::getOrchestrator()
            ->rewriteAllContext();
    }
}
