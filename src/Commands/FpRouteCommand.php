<?php

namespace Fp\FullRoute\Commands;

use Fp\FullRoute\Entities\FpNavigation;
use Fp\FullRoute\Services\DevelopmentSetup\DevelopmentSetup;
use Fp\FullRoute\Entities\FpRoute;
use Fp\FullRoute\Services\Route\FullRouteInteractive;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use function Laravel\Prompts\select;


class FpRouteCommand extends Command
{
    // variables necesarias (opcionales)
    protected $signature = 'fp:route 
                            {--delete : Eliminar una ruta existente} 
                            {--rewrite : reescribe todos los archivos de rutas (futuro)}
                            {--new : Crear una nueva ruta (futuro)}
                            {--id= : ID de la ruta a procesar} 
                            {--parentId= : ID del padre (opcional)}';

    protected $description = 'Comando para gestionar rutas FpFullRoute';

    protected FullRouteInteractive $interactive;

    public function handle()
    {
        


        //$seleccion = FpRoute::seleccionar();

        //dd($seleccion);


        //$routes = FpRoute::all();

        // dd($routes);

        $this->interactive = new FullRouteInteractive();

        // --delete, --new, --move
        if ($this->option('delete')) {
            $this->interactive->eliminar($this->option('id'));
            return;
        }

        if ($this->option('new')) {
            // id 
            $data['id'] = $this->option('id');
            // parentId
            $data['parentId'] = $this->option('parentId');
            $this->interactive->crear($data);
            return;
        }
        if ($this->option('rewrite')) {
            $this->interactive->reescribir();
            return;
        }

        $this->menuInteractivo();
        // Otros casos como --new, --move irán aquí...
        $this->info('¡Hola desde tu paquete FullRoute!');
    }

    protected function menuInteractivo()
    {
        $opcion = select(
            label: 'Selecciona una opción',
            options: [
                'nueva' => '🛠️ Crear nueva ruta',
                'eliminar' => '🗑️ Eliminar ruta existente',
                'reescribir' => '🔄 Reescribir rutas',
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
