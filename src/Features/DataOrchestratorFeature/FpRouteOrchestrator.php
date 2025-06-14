<?php

namespace Fp\RoutingKit\Features\DataOrchestratorFeature;

use Fp\RoutingKit\Contracts\FpOrchestratorInterface;
use Fp\RoutingKit\Contracts\FpContextEntitiesInterface;
use RuntimeException;

class FpRouteOrchestrator extends FpBaseOrchestrator implements FpOrchestratorInterface
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
     * @return FpContextEntitiesInterface|null // Usando la interfaz directamente
     */
    public function getDefaultContext(): ?FpContextEntitiesInterface
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

    

}
