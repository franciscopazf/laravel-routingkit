<?php

namespace FP\RoutingKit\Commands;

use FP\RoutingKit\Features\RolesAndPermissionsFeature\DevelopmentSetup;
use Illuminate\Console\Command;

class FPAccess extends Command
{
    // variables necesarias (opcionales)
    protected $signature = 'fp:access
                            {--force : Fuerza la sincronización de accesos sin confirmación}
                            {--dry-run : Simula los cambios sin aplicarlos}
                            {--only-permissions : Solo sincroniza permisos}
                            {--only-roles : Solo sincroniza roles}
                            {--no-assign : No asigna permisos a roles}
                            {--reset : Elimina roles y permisos antes de sincronizar}
                            {--role= : Restringe sincronización a ciertos roles}
                            {--entity= : Clase FPEntity personalizada}
';

    protected $description = 'Comando para sincronizar accesos de rutas FPRoute';

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
