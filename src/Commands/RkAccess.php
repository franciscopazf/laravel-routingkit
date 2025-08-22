<?php

namespace Rk\RoutingKit\Commands;

use Rk\RoutingKit\Features\RolesAndPermissionsFeature\DevelopmentSetup;
use Illuminate\Console\Command;

class RkAccess extends Command
{
    // variables necesarias (opcionales)
    protected $signature = 'rk:access
                            {--tenants : Sincroniza todos los inquilinos}
                            {--tenant= : ID del inquilino}
                            {--force : Fuerza la sincronización de accesos sin confirmación}
                            {--dry-run : Simula los cambios sin aplicarlos}
                            {--only-permissions : Solo sincroniza permisos}
                            {--only-roles : Solo sincroniza roles}
                            {--no-assign : No asigna permisos a roles}
                            {--reset : Elimina roles y permisos antes de sincronizar}
                            {--role= : Restringe sincronización a ciertos roles}
                            {--entity= : Clase RkEntity personalizada}
';

    protected $description = 'Comando para sincronizar accesos de rutas RkRoute';

    public function handle()
    {


        // confirmar si se desea continuar
        if (!$this->option('force')) {
            if (!$this->confirm('¿Estás seguro de que deseas sincronizar los accesos?')) {
                $this->info('Sincronización cancelada.');
                return;
            }
        }
        $tenantId = $this->option('tenant') ? $this->option('tenant') : null;
        $tenants = $this->option('tenants') ? $this->option('tenants') : null;

        DevelopmentSetup::make($tenantId, $tenants)
            ->run();
    }
}
