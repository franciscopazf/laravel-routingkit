<?php

namespace Rk\RoutingKit\Commands;

use Rk\RoutingKit\Entities\RkNavigation;
use Rk\RoutingKit\Entities\RkRoute;
use Rk\RoutingKit\Features\InteractiveFeature\RkInteractiveNavigator;
use Rk\RoutingKit\Features\InteractiveFeature\RkParameterOrchestrator;


use Illuminate\Console\Command;
use function Laravel\Prompts\select;


class RkNavigationCommand extends Command
{
    // variables necesarias (opcionales)
    protected $signature = 'rk:navigation
                            {--delete : Eliminar una ruta existente} 
                            {--rewrite : reescribe todos los archivos de navegación}
                            {--new : Crear una nueva navegación}
                            {--id= : ID de la navegación (opcional)}
                            {--parentId= : ID del padre (opcional)}';

    protected $description = 'Comando para gestionar rutas RkRoutingKit';

    protected RkInteractiveNavigator $interactive;

    public function handle()
    {
        $this->interactive = RkInteractiveNavigator::make(RkNavigation::class);

        if ($this->option('delete')) {
            $this->interactive->eliminar($this->option('id'));
            return;
        }

        if ($this->option('new')) {
            $data['id'] = $this->option('id');
            $data['parentId'] = $this->option('parentId');
            $this->interactive->crear($data);
            return;
        }
        if ($this->option('rewrite')) {
            $this->interactive->reescribir();
            return;
        }

        $this->menuInteractivo();
        $this->info('Exito, la operación se ha completado correctamente.');
    }

    protected function menuInteractivo()
    {
        $opcion = select(
            label: 'Selecciona una opción',
            options: [
                'nueva' => '🛠️ Nueva Navegacion',
                'eliminar' => '🗑️ Eliminar Navegacion',
                'reescribir' => '🔄 Reescribir Navegacion',
                'salir' => '🚪 Salir',
            ]
        );

        match ($opcion) {
            'nueva' => $this->interactive->crear(),
            'eliminar' => $this->interactive->eliminar(),
            'reescribir' => $this->interactive->reescribir(),
            'salir' => $this->info('Saliendo...'),
        };
    }
}
