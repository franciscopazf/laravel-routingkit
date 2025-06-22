<?php

namespace FpF\RoutingKit\Commands;

use FpF\RoutingKit\Entities\FpFNavigation;
use FpF\RoutingKit\Entities\FpFRoute;
use FpF\RoutingKit\Features\InteractiveFeature\FpFInteractiveNavigator;
use FpF\RoutingKit\Features\InteractiveFeature\FpFParameterOrchestrator;


use Illuminate\Console\Command;
use function Laravel\Prompts\select;


class FpFNavigationCommand extends Command
{
    // variables necesarias (opcionales)
    protected $signature = 'fpf:navigation
                            {--delete : Eliminar una ruta existente} 
                            {--rewrite : reescribe todos los archivos de navegación}
                            {--new : Crear una nueva navegación}
                            {--id= : ID de la navegación (opcional)}
                            {--parentId= : ID del padre (opcional)}';

    protected $description = 'Comando para gestionar rutas FpFRoutingKit';

    protected FpFInteractiveNavigator $interactive;

    public function handle()
    {
        $this->interactive = FpFInteractiveNavigator::make(FpFNavigation::class);

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
