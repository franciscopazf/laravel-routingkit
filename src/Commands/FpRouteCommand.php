<?php

namespace Fp\FullRoute\Commands;

use Fp\FullRoute\Services\FullRouteInteractive;
use Illuminate\Console\Command;
use function Laravel\Prompts\select;

use Fp\FullRoute\Helpers\Navigator;


class FpRouteCommand extends Command
{
    // variables necesarias (opcionales)
    protected $signature = 'fp:route 
                            {--delete : Eliminar una ruta existente} 
                            {--new : Crear una nueva ruta (futuro)} 
                            {--move : Mover una ruta (futuro)} 
                            {--id= : ID de la ruta a procesar} 
                            {--parentId= : ID del padre (opcional)}';

    protected $description = 'Comando para gestionar rutas FpFullRoute';

    protected FullRouteInteractive $interactive;

    public function handle()
    {
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

        if ($this->option('move')) {
            $this->interactive->mover($this->option('id'), $this->option('parentId'));
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
                'mover' => '🔁 Mover ruta existente',
                'eliminar' => '🗑️ Eliminar ruta existente',
                'salir' => '🚪 Salir',
            ]
        );

        match ($opcion) {
            'nueva' => $this->interactive->crear(),
            'mover' => $this->interactive->mover(),
            'eliminar' => $this->interactive->eliminar(),
            'salir' => $this->info('Saliendo...'),
        };
    }
}
