<?php

namespace Fp\RoutingKit\Commands;

use Fp\RoutingKit\Services\Route\RoutingKitInteractive;
use Illuminate\Console\Command;
use function Laravel\Prompts\select;

use Fp\RoutingKit\Helpers\Navigator;
use Fp\RoutingKit\Clases\RoutingKit;
use Fp\RoutingKit\Services\Navigator\Navigator as NNavigator;
use Fp\RoutingKit\Services\Route\RouteOrchestrator;

class FpChangeSupportFile extends Command
{
    // variables necesarias (opcionales)
    protected $signature = 'fp:rebuild-routes
                            {--force : Fuerza la reconstrucción de las rutas sin confirmación}';

    protected $description = 'Esta orden reconstruye las rutas de la aplicación.';

    protected RoutingKitInteractive $interactive;

    public function handle()
    {
        

        // si se pasa la bandera --force se llama al metodo 
        // forceRebuild() y se reconstruyen las rutas sin confirmación
        if ($this->option('force')) {
            $this->forceRebuild();
            return;
        }

        RouteOrchestrator::make()
            ->rebuildContent();
    }

    public function forceRebuild(): bool
    {
        RouteOrchestrator::make()
            ->rebuildContent(force: true);
        $this->info('Rutas reconstruidas exitosamente.');
        return true;
    }
}
