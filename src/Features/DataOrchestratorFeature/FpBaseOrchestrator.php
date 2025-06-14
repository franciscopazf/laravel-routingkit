<?php

namespace Fp\RoutingKit\Features\DataOrchestratorFeature;

use App\Models\User;
use Fp\RoutingKit\Contracts\FpEntityInterface;
use Fp\RoutingKit\Contracts\FpOrchestratorInterface;
use Fp\RoutingKit\Contracts\FpContextEntitiesInterface;
use Fp\RoutingKit\Features\DataOrchestratorFeature\FpVarsOrchestratorTrait;
use Fp\RoutingKit\Features\DataContextFeature\FpFileDataContext;
use Fp\RoutingKit\Features\RepositoryFeature\FpRepositoryFactory;
use Fp\RoutingKit\Contracts\FpRepositoryInterface;
use Fp\RoutingKit\Features\DataContextFeature\FpDataContextFactory;
use Illuminate\Support\Collection;
use RuntimeException;

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
     * @param string $key
     * @return FpContextEntitiesInterface
     */
    protected function getContextInstance(string $key): FpContextEntitiesInterface
    {
        if (!isset($this->contextConfigurations[$key])) {
            throw new RuntimeException("Configuración de contexto para la clave '{$key}' no encontrada.");
        }

        $contextData = $this->contextConfigurations[$key];
        $contextData['key'] = $key;

        return $this->prepareContext($contextData);
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
     * @return FpContextEntitiesInterface|null
     */
    public function getDefaultContext(): ?FpContextEntitiesInterface
    {
        $defaultKey = $this->getDefaultContextKey();

        if ($defaultKey) {
            try {
                return $this->getContextInstance($defaultKey);
            } catch (RuntimeException $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Ya no se utiliza, pero se mantiene si la interfaz lo exige.
     */
    protected function getContextsConfigPath(): string
    {
        return '';
    }

    /**
     * Guarda una entidad.
     */
    public function save(FpEntityInterface $entity, string|FpEntityInterface|null $parent = null): void
    {
        // Implementación de guardado
    }

    /**
     * Elimina una entidad.
     */
    public function delete(FpEntityInterface $entity): bool
    {
        return true;
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
