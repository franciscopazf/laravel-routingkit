<?php

namespace FpF\RoutingKit\Commands;

use FpF\RoutingKit\Features\RolesAndPermissionsFeature\DevelopmentSetup;
use Illuminate\Console\Command;

class FpFAccess extends Command
{
    // variables necesarias (opcionales)
    protected $signature = 'fpf:access
                            {--force : Fuerza la sincronización de accesos sin confirmación}
                            {--dry-run : Simula los cambios sin aplicarlos}
                            {--only-permissions : Solo sincroniza permisos}
                            {--only-roles : Solo sincroniza roles}
                            {--no-assign : No asigna permisos a roles}
                            {--reset : Elimina roles y permisos antes de sincronizar}
                            {--role= : Restringe sincronización a ciertos roles}
                            {--entity= : Clase FpFEntity personalizada}
';

    protected $description = 'Comando para sincronizar accesos de rutas FpFRoute';

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
