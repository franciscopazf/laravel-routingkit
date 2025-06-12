<?php

namespace Fp\FullRoute\Services\Route\Orchestrator;

use Fp\FullRoute\Contracts\OrchestratorInterface;
use Fp\FullRoute\Contracts\RouteStrategyInterface; // Usar el contrato RouteStrategyInterface directamente
use Fp\FullRoute\Services\Route\Strategies\RouteStrategyFactory;
// Si RouteContext es tu implementación concreta de RouteStrategyInterface, y no otra interfaz:
// use Fp\FullRoute\Services\Route\RouteContext;
use RuntimeException;

class NavigatorOrchestrator extends BaseOrchestrator implements OrchestratorInterface
{
    protected static ?self $instance = null;

    /**
     * Implementación del patrón Singleton para el NavigatorOrchestrator.
     * @return self
     */
    public static function make(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Implementa el método abstracto de BaseOrchestrator.
     * Define la ruta de configuración específica para los contextos de NavigatorOrchestrator.
     * @return string
     */
    protected function getContextsConfigPath(): string
    {
        return 'fproute.navigators_file_path.items'; // La ruta a tu array de configuraciones de contextos
    }

    /**
     * Implementa el método abstracto de BaseOrchestrator.
     * Prepara una instancia de RouteStrategyInterface a partir de los datos de configuración.
     *
     * @param array $contextData Array de configuración para un contexto específico.
     * @return RouteStrategyInterface // Usando la interfaz directamente
     * @throws RuntimeException Si la configuración no es válida.
     */
    protected function prepareContext(array $contextData): RouteStrategyInterface // Usando la interfaz directamente
    {
        if (!isset($contextData['support_file']) || !isset($contextData['path'])) {
            throw new RuntimeException("Configuración de contexto inválida: 'support_file' o 'path' faltantes para el contexto.");
        }

        $context = RouteStrategyFactory::make(
            $contextData['support_file'],
            $contextData['path'],
            $contextData['only_string_support'] ?? true
        );

        // Asegúrate de que $context sea una instancia de RouteStrategyInterface
        if (!$context instanceof RouteStrategyInterface) {
            throw new RuntimeException("La estrategia de ruta no devolvió una instancia de RouteStrategyInterface.");
        }

        return $context;
    }

    /**
     * Obtiene la clave del contexto por defecto.
     * @return string|null
     */
    public function getDefaultContextKey(): ?string
    {
        $position = config('fproute.navigators_file_path.defaul_file_path_position', 0);
        $keys = $this->getContextKeys();

        if (isset($keys[$position])) {
            return $keys[$position];
        }
        return null;
    }

    /**
     * Obtiene la instancia del contexto por defecto.
     * @return RouteStrategyInterface|null // Usando la interfaz directamente
     */
    public function getDefaultContext(): ?RouteStrategyInterface // Usando la interfaz directamente
    {
        $defaultKey = $this->getDefaultContextKey();
        if ($defaultKey) {
            try {
                return $this->getContextInstance($defaultKey);
            } catch (RuntimeException $e) {
                // Loguear el error si el contexto por defecto no se puede cargar
                // Log::error("Error loading default context '{$defaultKey}': " . $e->getMessage());
                return null;
            }
        }
        return null;
    }

    // Los métodos getAllOnlyRolHasPermissions y getAllInPermissionsList
    // siguen lanzando una excepción si no están implementados.
    public function getAllOnlyRolHasPermissions($role): array
    {
        throw new \BadMethodCallException(__FUNCTION__ . " no está implementado o ha sido reemplazado. Usa 'getFilteredWithPermissions' o 'getAllOfCurrenUser'.");
    }

    public function getAllInPermissionsList(array $permissions): array
    {
        throw new \BadMethodCallException(__FUNCTION__ . " no está implementado o ha sido reemplazado. Usa 'getFilteredWithPermissions'.");
    }
}