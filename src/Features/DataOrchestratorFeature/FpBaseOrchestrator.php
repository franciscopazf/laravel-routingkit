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
        // Ya no es necesario llamar a loadKeyInConfiguration aquí de esta forma,
        // ya que la clave se establecerá en loadAllContextConfigurations.
        // $this->loadKeyInConfiguration($this->configurations);
    }

    public static function make(array $configurations): FpOrchestratorInterface
    {
        return new static($configurations);
    }

    /**
     * Este método no es necesario si la clave se añade directamente en loadAllContextConfigurations.
     * Su lógica actual modifica $this->configurations globalmente, no los elementos individuales.
     */
    // public function loadKeyInConfiguration(array $config): array
    // {
    //     foreach ($config as $key => $value) {
    //         if (is_array($value)) {
    //             $this->loadKeyInConfiguration($value);
    //         } else {
    //             // Aseguramos que la clave esté presente en la configuración
    //             if (!isset($this->configurations[$key])) {
    //                 $this->configurations[$key] = $value;
    //             }
    //         }
    //     }
    //     return $this->configurations;
    // }

    /**
     * Carga todas las configuraciones de los contextos desde el arreglo recibido.
     * La clave del arreglo externo se asigna a cada configuración de contexto individual.
     */
    protected function loadAllContextConfigurations(): void
    {
        $configsFromInput = $this->configurations['items'] ?? [];

        if (!is_array($configsFromInput)) {
            throw new RuntimeException("La configuración 'items' no es un array válido.");
        }

        foreach ($configsFromInput as $contextKey => $config) {
            if (!isset($config['path']) || !isset($config['support_file'])) {
                throw new RuntimeException("Configuración de contexto inválida para la clave '{$contextKey}'. Debe contener 'path' y 'support_file'.");
            }

            // Aseguramos que la clave del arreglo externo (contextKey)
            // se establezca dentro del propio arreglo de configuración del contexto.
            $config['key'] = $contextKey;

            // Aseguramos que la clave sea única en la caché de configuraciones.
            if (isset($this->contextConfigurations[$contextKey])) {
                throw new RuntimeException("La clave de contexto '{$contextKey}' ya está definida.");
            }

            // Asignamos la configuración modificada al arreglo de contextos del orquestador.
            $this->contextConfigurations[$contextKey] = $config;
        }

        // Ya no sobrescribimos this->contextConfigurations con $configsFromInput,
        // ya que lo hemos llenado elemento por elemento con la clave asignada.
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
        // dd("Preparando contexto con datos:", $contextData); // Puedes descomentar para depurar
        if (!isset($contextData['support_file']) || !isset($contextData['path'])) {
            throw new RuntimeException("Configuración de contexto inválida: 'support_file' o 'path' faltantes.");
        }
        // Asegúrate de que 'key' esté presente en $contextData
        if (!isset($contextData['key'])) {
            throw new RuntimeException("La clave de contexto ('key') no está presente en los datos de configuración. Esto debería haberse establecido en loadAllContextConfigurations.");
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

    // reescribe todos los contextos en cache para reescribirlos
    public function rewriteAllContext(array $contextKeys = []): void
    {
        // Si se pasan claves de contexto, solo reescribimos esos contextos.
        if (!empty($contextKeys)) {
            foreach ($contextKeys as $key) {
                if (isset($this->contextInstancesCache[$key])) {
                    $this->contextInstancesCache[$key]->rewriteAllEntities();
                }
            }
            return;
        }

        // Si no se pasan claves, reescribimos todos los contextos sin importar si están cacheados o no.
        foreach ($this->contextConfigurations as $key => $config) {
            // dd("Reescribiendo contexto: {$key}", $config); // Puedes descomentar para depurar
            if (isset($this->contextInstancesCache[$key])) {
                $this->contextInstancesCache[$key]->rewriteAllEntities();
            } else {
                // Si no está en caché, lo creamos y reescribimos.
                $context = $this->prepareContext($config);
                $context->rewriteAllEntities();
                $this->contextInstancesCache[$key] = $context;
            }
        }
    }


    /**
     * Verifica si una entidad existe por ID.
     */
    public function exists(string $id): bool
    {
        return $this->findById($id) !== null;
    }
}