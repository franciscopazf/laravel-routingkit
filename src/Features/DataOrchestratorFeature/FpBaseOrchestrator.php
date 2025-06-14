<?php

namespace Fp\RoutingKit\Features\DataOrchestratorFeature;

use Fp\RoutingKit\Contracts\FpEntityInterface;
use Fp\RoutingKit\Contracts\FpOrchestratorInterface;
use Fp\RoutingKit\Contracts\FpContextEntitiesInterface;
use Fp\RoutingKit\Features\DataOrchestratorFeature\FpVarsOrchestratorTrait;
use Fp\RoutingKit\Features\DataContextFeature\FpDataContextFactory;
use RuntimeException;
use Illuminate\Support\Collection; // Necesario para Collection en FpVarsOrchestratorTrait


class FpBaseOrchestrator implements FpOrchestratorInterface
{
    use FpVarsOrchestratorTrait {
        __construct as private initializeVarsOrchestratorTrait;
    }

    /**
     * @var array Cache para las configuraciones de contexto cargadas.
     * Key: contextKey, Value: array de configuración del contexto.
     */
    protected array $contextConfigurations = [];

    /**
     * @var FpContextEntitiesInterface[] Cache para las instancias de contexto.
     * Key: contextKey, Value: FpContextEntitiesInterface instance.
     * Se cargan de forma perezosa.
     */
    protected array $contextInstancesCache = []; // ¡NUEVA CACHÉ para instancias de contexto!

    protected array $configurations;

    /**
     * Constructor del Orchestrator.
     */
    public function __construct(array $configurations)
    {
        $this->configurations = $configurations;
        $this->initializeVarsOrchestratorTrait();
        $this->loadAllContextConfigurations();
        $this->currentIncludedContextKeys = $this->getContextKeys();
    }

    public static function make(array $configurations): FpOrchestratorInterface
    {
        return new static($configurations);
    }

    /**
     * Carga todas las configuraciones de los contextos desde el arreglo recibido.
     */
    protected function loadAllContextConfigurations(): void
    {
        $configs = $this->configurations['items'] ?? [];

        if (!is_array($configs)) {
            throw new RuntimeException("La configuración 'items' no es un array válido.");
        }

        $this->contextConfigurations = $configs;
    }

    /**
     * Devuelve una lista de todas las claves de contexto disponibles.
     * @return array
     */
    public function getContextKeys(): array
    {
        return array_keys($this->contextConfigurations);
    }

    /**
     * Retorna una instancia del contexto de ruta para una clave dada.
     * Carga la instancia de forma perezosa y la cachea por defecto.
     * Permite forzar una nueva carga si `forceNew` es true.
     *
     * @param string $key La clave del contexto.
     * @param bool $forceNew Si es true, ignora la caché y crea una nueva instancia.
     * @return FpContextEntitiesInterface
     * @throws RuntimeException
     */
    protected function getContextInstance(string $key, bool $forceNew = false): FpContextEntitiesInterface
    {
        // Si no se fuerza una nueva instancia y ya está en caché, la devolvemos.
        if (!$forceNew && isset($this->contextInstancesCache[$key])) {
            return $this->contextInstancesCache[$key];
        }

        if (!isset($this->contextConfigurations[$key])) {
            throw new RuntimeException("Configuración de contexto para la clave '{$key}' no encontrada.");
        }

        $contextData = $this->contextConfigurations[$key];
        $contextData['key'] = $key;

        // Preparamos la nueva instancia del contexto
        $instance = $this->prepareContext($contextData);

        // La cacheamos antes de devolverla
        $this->contextInstancesCache[$key] = $instance;

        return $instance;
    }

    /**
     * Prepara una instancia del contexto a partir de los datos de configuración.
     * @param array $contextData
     * @return FpContextEntitiesInterface
     */
    protected function prepareContext(array $contextData): FpContextEntitiesInterface
    {
        if (!isset($contextData['support_file']) || !isset($contextData['path'])) {
            throw new RuntimeException("Configuración de contexto inválida: 'support_file' o 'path' faltantes.");
        }

        return FpDataContextFactory::getDataContext(
            $contextData['key'],
            $contextData['path'],
            $contextData['support_file'],
            $contextData['only_string_support'] ?? false
        );
    }

    /**
     * Obtiene la clave del contexto por defecto.
     * @return string|null
     */
    public function getDefaultContextKey(): ?string
    {
        $position = $this->configurations['default_file'] ?? 0;
        $keys = $this->getContextKeys();

        return $keys[$position] ?? null;
    }

    /**
     * Obtiene la instancia del contexto por defecto.
     * @param bool $forceNew Si es true, fuerza la obtención de una nueva instancia.
     * @return FpContextEntitiesInterface|null
     */
    public function getDefaultContext(bool $forceNew = false): ?FpContextEntitiesInterface
    {
        $defaultKey = $this->getDefaultContextKey();

        if ($defaultKey) {
            try {
                // Ahora usa el método getContextInstance con la opción forceNew
                return $this->getContextInstance($defaultKey, $forceNew);
            } catch (RuntimeException $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Agrega una entidad al contexto al que se supone que pertenece.
     *
     * @param FpEntityInterface $entity La entidad a agregar.
     * @param string|null $contextKey Opcional. La clave del contexto al que se debe agregar la entidad.
     * Si es null, intentará usar el contextKey de la entidad, o el contexto por defecto.
     * @return bool True si la entidad fue agregada con éxito, false en caso contrario.
     * @throws RuntimeException Si no se puede determinar el contexto o el contexto no soporta 'add'.
     */
    public function add(FpEntityInterface $entity, ?string $contextKey = null): bool
    {
        if ($contextKey === null) {
            $contextKey = $entity->getContextKey() ?? $this->getDefaultContextKey();
        }

        if ($contextKey === null) {
            throw new RuntimeException("No se pudo determinar el contexto para agregar la entidad '{$entity->getId()}'. Especifique un contextKey o asegúrese de que la entidad lo tenga.");
        }

        // Obtener la instancia del contexto (ahora cacheada por defecto)
        $context = $this->getContextInstance($contextKey);

        if (!($context instanceof FpContextEntitiesInterface) || !method_exists($context, 'add')) {
            throw new RuntimeException("El contexto '{$contextKey}' no implementa el método 'add' necesario para agregar entidades.");
        }

        $isAdded = $context->add($entity);

        // Si la adición fue exitosa, invalidar las cachés relevantes del orquestador
        if ($isAdded) {
            $this->invalidateOrchestratorCaches($contextKey);
        }

        return $isAdded;
    }

    /**
     * Elimina una entidad del contexto al que pertenece.
     *
     * @param FpEntityInterface $entity La entidad a eliminar.
     * @return bool True si se eliminó con éxito, false en caso contrario.
     * @throws RuntimeException Si la entidad no tiene un contextKey o el contexto no soporta 'delete'.
     */
    public function delete(FpEntityInterface $entity): bool
    {
        $contextKey = $entity->getContextKey();
        
        if ($contextKey === null) {
            throw new RuntimeException("La entidad '{$entity->getId()}' no tiene asignado un contextKey. Asegúrate de que las entidades se carguen correctamente con su contexto.");
        }

        if (!isset($this->contextConfigurations[$contextKey])) {
            throw new RuntimeException("Configuración del contexto '{$contextKey}' no encontrada para la entidad '{$entity->getId()}'.");
        }

        // Obtener la instancia del contexto (ahora cacheada por defecto)
        $context = $this->getContextInstance($contextKey);

        if (!($context instanceof FpContextEntitiesInterface) || !method_exists($context, 'removeEntity')) {
            throw new RuntimeException("El contexto '{$contextKey}' no implementa el método 'removeEntity' necesario para eliminar entidades.");
        }

        // Asumo que el método removeEntity del contexto toma el ID de la entidad.
        $isDeleted = $context->removeEntity($entity->getId());

        // Si la eliminación fue exitosa, invalidar las cachés relevantes del orquestador.
        if ($isDeleted) {
            $this->invalidateOrchestratorCaches($contextKey);
        }

        return $isDeleted;
    }

    /**
     * Helper interno para invalidar las cachés relevantes del orquestador después de una operación de modificación.
     *
     * @param string $modifiedContextKey La clave del contexto que fue modificado.
     */
    protected function invalidateOrchestratorCaches(string $modifiedContextKey): void
    {
        // Al modificar un contexto, debemos invalidar su instancia cacheada también,
        // para que la próxima vez que se pida, se cree una nueva con los datos frescos.
        unset($this->contextInstancesCache[$modifiedContextKey]);

        // Invalidar la caché específica de ese contexto en el trait (Collection of flattened entities).
        $this->contextualCachedEntities->forget($modifiedContextKey);

        // Invalidar las cachés globales (árbol y aplanadas).
        $this->globalTreeAllEntities = null;
        $this->globalFlattenedAllEntities = null;

        // Invalidar la caché de resultados filtrados.
        $this->filteredEntitiesCache = null;
    }


    /**
     * Busca una entidad por ID en el arreglo plano.
     */
    public function findById(string $id): ?FpEntityInterface
    {
        $allFlattened = $this->getRawGlobalFlattened();
        return $allFlattened->get($id);
    }

    /**
     * Busca una entidad por ID incluyendo sus hijos.
     */
    public function findByIdWithItems(string $id): ?FpEntityInterface
    {
        $tree = $this->getRawGlobalTree();
        $flattened = $this->flattenTree($tree);
        return $flattened->get($id);
    }

    /**
     * Verifica si una entidad existe por ID.
     */
    public function exists(string $id): bool
    {
        return $this->findById($id) !== null;
    }
}