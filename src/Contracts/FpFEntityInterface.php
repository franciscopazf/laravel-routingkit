<?php

namespace FpF\RoutingKit\Contracts;

use FpF\RoutingKit\Contracts\OrchestratorInterface;
use Illuminate\Support\Collection;


interface FpFEntityInterface
{

    // CRUD Methods

    /**
     * Set the ID of the entity.
     *
     * @return FpFEntityInterface
     */
    public function setId(string $id): FpFEntityInterface;


    /**
     * Set the parent entity.
     *
     * @param FpFEntityInterface $parent
     * @return FpFEntityInterface
     */
    public function setParent(FpFEntityInterface $parent): FpFEntityInterface;

    /**
     * Set the parent entity ID.
     *
     * @param string $parentId
     * @return FpFEntityInterface
     */
    public function setParentId(string $parentId): FpFEntityInterface;
    

    /**
     * Get the parent entity ID.
     *
     * @return string|null
     */
    public function getParentId(): ?string;

    /**
     * Get the ID of the entity.
     *
     * @return string
     */
    public function getId(): string;



    /**
     * Create a new entity instance.
     *
     * @param string $id
     * @return FpFEntityInterface
     */
    public static function make(string $id): FpFEntityInterface;

    /**
     * Save permanently the entity, optionally setting a parent.
     *
     * @param string|FpFEntityInterface|null $parent
     * @return self
     */
    public function save(string|FpFEntityInterface|null $parent = null): self;

    /**
     * Delete the entity permanently.
     *
     * 
     * @return bool
     */
    public function delete(): bool;

    /**
     * Find an entity by its ID.
     *
     * @param string $id
     * @return FpFEntityInterface|null
     */
    public static function findById(string $id): ?FpFEntityInterface;

    /**
     * Find an entity by its ID, or throw an exception if not found.
     *
     * @param string $id
     * @return FpFEntityInterface
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
  //  public static function findByParamName(string $paramName, string $value): ?Collection;

    /**
     * get all entities.
     * @return Collection
     */
    public static function all(): Collection;

    /**
     * Get all entities grouped by file.
     *
     * @return Collection
     */
  //  public static function getAllsByFile(string $filePath): Collection;

    /**
     * Get all entities grouped by file, flattened.
     *
     * @return Collection
     */
 //   public static function getAllsByFileFlattened(): Collection;
    

    /**
     * Get all entities in a flattened structure.
     *
     * @return Collection
     */
    public static function allFlattened(): Collection;

    /**
     * Get all entities in a tree structure.
     *
     * @return Collection
     */
    //public static function allInTree(): Collection;


    // Utility Methods

    /**
     * Check if an entity exists by its ID.
     *
     * @param string $id
     * @return bool
     */
    public static function exists(string $id): bool;

    
    /**
     * Check if an entity is a child of another entity.
     *
     * @param string $id
     * @return bool
     */
    public function isChild(string|FpFEntityInterface $entity): bool;

    /**
     * returns the parent entity of the current entity.
     *
     * @param string $id
     * @return bool
     */
  //  public function parent(string|FpFEntityInterface $parent): FpFEntityInterface;

    /**
     * Get the breadcrumbs for the entity. in the tree structure.
     *
     * @return Collection
     */
   //  public static function getBreadcrumbs(string|FpFEntityInterface $entity): Collection;

    /**
     * Move the entity to a new parent.
     *
     * @return FpFEntityInterface
     */
    public function moveTo(string|FpFEntityInterface $parent): self;


    /**
     * Get the ID of the entity.
     *
     * @return string
     */
    public function getParent(): ?FpFEntityInterface;

    /**
     * add a child to the current entity.
     *
     * @return self
     */
    public function addItem(FpFEntityInterface $child): self;


    // Context Methods


    


    
    /**
     * Get the context of the entity in a human-readable format.
     *
     * @return string
     */
    public static function seleccionar(?string $omitId = null, string $label = 'Selecciona una ruta'): ?string;

    /**
     * Get the properties of the entity.
     *
     * @return array
     */
    public function getProperties(): array;

    /**
     * Get the properties of the entity in a human-readable format.
     *
     * @return array
     */
    public static function rewriteAllContext(): void;
}
