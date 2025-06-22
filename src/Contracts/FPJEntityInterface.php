<?php

namespace FPJ\RoutingKit\Contracts;

use FPJ\RoutingKit\Contracts\OrchestratorInterface;
use Illuminate\Support\Collection;


interface FPJEntityInterface
{

    // CRUD Methods

    /**
     * Set the ID of the entity.
     *
     * @return FPJEntityInterface
     */
    public function setId(string $id): FPJEntityInterface;


    /**
     * Set the parent entity.
     *
     * @param FPJEntityInterface $parent
     * @return FPJEntityInterface
     */
    public function setParent(FPJEntityInterface $parent): FPJEntityInterface;

    /**
     * Set the parent entity ID.
     *
     * @param string $parentId
     * @return FPJEntityInterface
     */
    public function setParentId(string $parentId): FPJEntityInterface;
    

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
     * @return FPJEntityInterface
     */
    public static function make(string $id): FPJEntityInterface;

    /**
     * Save permanently the entity, optionally setting a parent.
     *
     * @param string|FPJEntityInterface|null $parent
     * @return self
     */
    public function save(string|FPJEntityInterface|null $parent = null): self;

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
     * @return FPJEntityInterface|null
     */
    public static function findById(string $id): ?FPJEntityInterface;

    /**
     * Find an entity by its ID, or throw an exception if not found.
     *
     * @param string $id
     * @return FPJEntityInterface
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
    public function isChild(string|FPJEntityInterface $entity): bool;

    /**
     * returns the parent entity of the current entity.
     *
     * @param string $id
     * @return bool
     */
  //  public function parent(string|FPJEntityInterface $parent): FPJEntityInterface;

    /**
     * Get the breadcrumbs for the entity. in the tree structure.
     *
     * @return Collection
     */
   //  public static function getBreadcrumbs(string|FPJEntityInterface $entity): Collection;

    /**
     * Move the entity to a new parent.
     *
     * @return FPJEntityInterface
     */
    public function moveTo(string|FPJEntityInterface $parent): self;


    /**
     * Get the ID of the entity.
     *
     * @return string
     */
    public function getParent(): ?FPJEntityInterface;

    /**
     * add a child to the current entity.
     *
     * @return self
     */
    public function addItem(FPJEntityInterface $child): self;


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
