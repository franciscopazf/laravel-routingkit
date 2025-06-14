<?php

namespace Fp\RoutingKit\Services\Route\Orchestrator;

use Fp\RoutingKit\Contracts\FpOrchestratorInterface;
use Fp\RoutingKit\Contracts\RouteStrategyInterface; // Usar el contrato RouteStrategyInterface directamente
use Fp\RoutingKit\Services\Route\Strategies\RouteStrategyFactory;
// Si RouteContext es tu implementación concreta de RouteStrategyInterface, y no otra interfaz:
// use Fp\RoutingKit\Services\Route\RouteContext;
use RuntimeException;

class RouteOrchestrator extends BaseOrchestrator implements FpOrchestratorInterface
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
        return 'routingkit.routes_file_path.items'; // La ruta a tu array de configuraciones de contextos
    }

    

    /**
     * Obtiene la clave del contexto por defecto.
     * @return string|null
     */
    public function getDefaultContextKey(): ?string
    {
        $position = config('routingkit.routes_file_path.defaul_file_path_position', 0);
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