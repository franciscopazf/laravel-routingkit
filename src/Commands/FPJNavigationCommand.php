<?php

namespace FPJ\RoutingKit\Commands;

use FPJ\RoutingKit\Entities\FPJNavigation;
use FPJ\RoutingKit\Entities\FPJRoute;
use FPJ\RoutingKit\Features\InteractiveFeature\FPJInteractiveNavigator;
use FPJ\RoutingKit\Features\InteractiveFeature\FPJParameterOrchestrator;


use Illuminate\Console\Command;
use function Laravel\Prompts\select;


class FPJNavigationCommand extends Command
{
    // variables necesarias (opcionales)
    protected $signature = 'fpj:navigation
                            {--delete : Eliminar una ruta existente} 
                            {--rewrite : reescribe todos los archivos de navegación}
                            {--new : Crear una nueva navegación}
                            {--id= : ID de la navegación (opcional)}
                            {--parentId= : ID del padre (opcional)}';

    protected $description = 'Comando para gestionar rutas FPJRoutingKit';

    protected FPJInteractiveNavigator $interactive;

    public function handle()
    {
        $this->interactive = FPJInteractiveNavigator::make(FPJNavigation::class);

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
