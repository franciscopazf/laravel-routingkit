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

abstract class FpBaseOrchestrator implements FpOrchestratorInterface
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
     * Constructor del Orchestrator.
     */
    public function __construct()
    {
        $this->initializeVarsOrchestratorTrait();
        $this->loadAllContextConfigurations();
        $this->currentIncludedContextKeys = $this->getContextKeys();
    }

    /**
     * Método abstracto que debe ser implementado por las clases derivadas
     * para especificar la ruta del archivo de configuración donde se encuentran
     * las definiciones de todos los contextos para este orquestador.
     *
     * Ejemplo: 'fproute.navigators_file_path.items'
     * @return string
     */
    abstract protected function getContextsConfigPath(): string;

    /**
     * Carga todas las configuraciones de los contextos desde el archivo de configuración.
     * Esto llena $this->contextConfigurations.
     * Este método se llama en el constructor de BaseOrchestrator.
     */
    protected function loadAllContextConfigurations(): void
    {
        $configPath = $this->getContextsConfigPath();
        $configs = config($configPath);

        if (!is_array($configs)) {
            throw new RuntimeException("La ruta de configuración '{$configPath}' no devuelve un array válido.");
        }

        $this->contextConfigurations = $configs;
    }

    /**
     * Devuelve una lista de todas las claves de contexto disponibles
     * basadas en las configuraciones cargadas.
     * @return array
     */
    public function getContextKeys(): array
    {
       // dd($this->contextConfigurations);
        return array_keys($this->contextConfigurations);
    }

    /**
     * Retorna una instancia del contexto de ruta para una clave dada.
     * @param string $key
     * @return FpContextEntitiesInterface
     * @throws RuntimeException Si el contexto no puede ser instanciado o configurado.
     */
    protected function getContextInstance(string $key): FpContextEntitiesInterface
    {
     //   dd($this->contextConfigurations, $key);
        if (!isset($this->contextConfigurations[$key])) {
          //  dd("Hola");
            throw new RuntimeException("Configuración de contexto para la clave '{$key}' no encontrada.");
        }
        $contextData = $this->contextConfigurations[$key];
        $contextData['key'] = $key; 
        $context = $this->prepareContext($contextData);
       // dd($context);
        return $context;
    }

    /**
     * Implementa el método abstracto de BaseOrchestrator.
     * Prepara una instancia de RouteStrategyInterface a partir de los datos de configuración.
     *
     * @param array $contextData Array de configuración para un contexto específico.
     * @return FpContextEntitiesInterface
     * @throws RuntimeException Si la configuración no es válida.
     */
    protected function prepareContext(array $contextData): FpContextEntitiesInterface
    {
        if (!isset($contextData['support_file']) || !isset($contextData['path'])) {
            throw new RuntimeException("Configuración de contexto inválida: 'support_file' o 'path' faltantes para el contexto.");
        }

        $context = FpDataContextFactory::getDataContext(
            $contextData['key'],
            $contextData['path'],
            $contextData['support_file'],
            $contextData['only_string_support'] ?? false
        );
        
        return $context;
    }

    // --- Otros métodos implementados o abstractos requeridos por OrchestratorInterface ---

    public function save(FpEntityInterface $entity, string|FpEntityInterface|null $parent = null): void
    {
        // Lógica para guardar la entidad
    }

    public function delete(FpEntityInterface $entity): bool
    {
        // Lógica para eliminar la entidad
        return true;
    }

    public function findById(string $id): ?FpEntityInterface
    {
        $allFlattened = $this->getRawGlobalFlattened();
        return $allFlattened->get($id);
    }

    /**
     * Encuentra una entidad por su ID, incluyendo sus elementos hijos.
     *
     * @param string $id
     * @return FpEntityInterface|null
     */
    public function findByIdWithItems(string $id): ?FpEntityInterface // <-- RENOMBRADO A findByIdWithItems
    {
        $tree = $this->getRawGlobalTree();
        $flattened = $this->flattenTree($tree);
        return $flattened->get($id);
    }

    public function exists(string $id): bool
    {
        return $this->findById($id) !== null;
    }
}
