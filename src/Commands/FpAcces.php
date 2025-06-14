<?php

namespace Fp\RoutingKit\Commands;

use Fp\RoutingKit\Services\DevelopmentSetup\DevelopmentSetup;
use Fp\RoutingKit\Features\DataRepositoryFeature\FpDataRepositoryFactory;
use Fp\RoutingKit\Services\Route\RoutingKitInteractive;

use Illuminate\Console\Command;
use Fp\RoutingKit\Features\DataContextFeature\FpDataContextFactory;
use Fp\RoutingKit\Features\DataOrchestratorFeature\FpBaseOrchestrator;
use Fp\RoutingKit\Features\DataOrchestratorFeature\FpRouteOrchestrator;

class FpAcces extends Command
{
    // variables necesarias (opcionales)
    protected $signature = 'fp:acces
                            {--force : Fuerza la sincronización de accesos sin confirmación}
                            {--dry-run : Simula los cambios sin aplicarlos}
                            {--only-permissions : Solo sincroniza permisos}
                            {--only-roles : Solo sincroniza roles}
                            {--no-assign : No asigna permisos a roles}
                            {--reset : Elimina roles y permisos antes de sincronizar}
                            {--role= : Restringe sincronización a ciertos roles}
                            {--entity= : Clase FpEntity personalizada}
';

    protected $description = 'Comando para sincronizar accesos de rutas FpRoute';

    protected RoutingKitInteractive $interactive;

    public function handle()
    {


        // confirmar si se desea continuar
        if (!$this->option('force')) {
            if (!$this->confirm('¿Estás seguro de que deseas sincronizar los accesos?')) {
                $this->info('Sincronización cancelada.');
                return;
            }
        }

        DevelopmentSetup::make()
            ->run();
    }
}
