<?php

namespace Fp\FullRoute\Commands;

use Fp\FullRoute\Entities\FpNavigation;
use Fp\FullRoute\Services\DevelopmentSetup\DevelopmentSetup;
use Fp\FullRoute\Entities\FpRoute;
use Fp\FullRoute\Services\Route\FullRouteInteractive;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use function Laravel\Prompts\select;


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

    protected FullRouteInteractive $interactive;

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
