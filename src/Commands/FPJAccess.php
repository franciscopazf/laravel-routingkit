<?php

namespace FPJ\RoutingKit\Commands;

use FPJ\RoutingKit\Features\RolesAndPermissionsFeature\DevelopmentSetup;
use Illuminate\Console\Command;

class FPJAccess extends Command
{
    // variables necesarias (opcionales)
    protected $signature = 'fpj:access
                            {--force : Fuerza la sincronización de accesos sin confirmación}
                            {--dry-run : Simula los cambios sin aplicarlos}
                            {--only-permissions : Solo sincroniza permisos}
                            {--only-roles : Solo sincroniza roles}
                            {--no-assign : No asigna permisos a roles}
                            {--reset : Elimina roles y permisos antes de sincronizar}
                            {--role= : Restringe sincronización a ciertos roles}
                            {--entity= : Clase FPJEntity personalizada}
';

    protected $description = 'Comando para sincronizar accesos de rutas FPJRoute';

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
