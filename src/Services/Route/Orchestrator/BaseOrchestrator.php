<?php

namespace Fp\RoutingKit\Services\Route\Orchestrator;

use Fp\RoutingKit\Contracts\OrchestratorInterface;
use Fp\RoutingKit\Contracts\RouteStrategyInterface; 
use Fp\RoutingKit\Contracts\FpEntityInterface; 
use Fp\RoutingKit\Services\Route\Strategies\RouteStrategyFactory;
use Illuminate\Support\Collection;
use RuntimeException;

abstract class BaseOrchestrator implements OrchestratorInterface
{
    use VarsOrchestratorTrait {
        // Asegúrate de que el constructor del trait se ejecute.
        // PHP 8+ permite llamar al constructor del trait directamente en el constructor de la clase.
        // Si no usas __construct() en el trait, puedes hacer esto:
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
        //echo "Inicializando BaseOrchestrator...\n";
        // Inicializa el trait primero.
        $this->initializeVarsOrchestratorTrait(); // Llama al constructor del trait

        // Cargar las configuraciones de los contextos.
        $this->loadAllContextConfigurations();

        // <--- NUEVO: Inicializar currentIncludedContextKeys con todos los contextos.
        // Esto garantiza que siempre esté poblado y nunca sea null al inicio de cualquier orquestador.
        // NOTA: Esto se mueve aquí si quieres que la inicialización ocurra DESPUÉS de que
        // loadAllContextConfigurations() haya llenado $this->contextConfigurations.
        // Si el trait ya tiene un constructor que llama a getContextKeys(), asegúrate
        // que loadAllContextConfigurations() ya se haya ejecutado.
        // La forma más segura es hacer que el trait NO tenga un constructor y que la inicialización
        // de currentIncludedContextKeys se haga en el constructor de BaseOrchestrator.
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
        return array_keys($this->contextConfigurations);
    }

    /**
     * Retorna una instancia del contexto de ruta para una clave dada.
     * @param string $key
     * @return RouteStrategyInterface 
     * @throws RuntimeException Si el contexto no puede ser instanciado o configurado.
     */
    protected function getContextInstance(string $key): RouteStrategyInterface 
    {
        if (!isset($this->contextConfigurations[$key])) {
            throw new RuntimeException("Configuración de contexto para la clave '{$key}' no encontrada.");
        }

        $contextData = $this->contextConfigurations[$key];
        $context = $this->prepareContext($contextData);
       
        return $context;
    }

    /**
     * Implementa el método abstracto de BaseOrchestrator.
     * Prepara una instancia de RouteStrategyInterface a partir de los datos de configuración.
     *
     * @param array $contextData Array de configuración para un contexto específico.
     * @return RouteStrategyInterface 
     * @throws RuntimeException Si la configuración no es válida.
     */
    protected function prepareContext(array $contextData): RouteStrategyInterface 
    {
        if (!isset($contextData['support_file']) || !isset($contextData['path'])) {
            throw new RuntimeException("Configuración de contexto inválida: 'support_file' o 'path' faltantes para el contexto.");
        }
       
        $context = RouteStrategyFactory::make(
            $contextData['support_file'],
            $contextData['path'],
            $contextData['only_string_support'] ?? true
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
