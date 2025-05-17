<?php

namespace Fp\FullRoute\Commands;

use Fp\FullRoute\Clases\FullRoute;
use Fp\FullRoute\Services\RouteService;

use Laravel\Prompts\prompt;
use function Laravel\Prompts\select;

use Illuminate\Console\Command;

class FpRouteCommand extends Command
{
    protected $signature = 'fp:route 
                            {--delete : Eliminar una ruta existente} 
                            {--new : Crear una nueva ruta (futuro)} 
                            {--move : Mover una ruta (futuro)} 
                            {--id= : ID de la ruta a procesar} 
                            {--parentId= : ID del padre (opcional)}';

    protected $description = 'Comando para gestionar rutas FpFullRoute';

    public function handle()
    {
        FullRoute::find('test2')
            ->moveTo('dashboard');
        //->delete(); // <- elimina la ruta

        FullRoute::make('test2')
            ->setPermission(fn() => 'admin')
            ->setTitle('Dashboard3')
            ->setDescription('Dashboard de la aplicacion')
            ->setKeywords('dashboard, fp-full-route')
            ->setIcon('fa-solid fa-house')
            ->setUrl('/dashboard3')
            ->setUrlName('dashboard3')
            ->setUrlMethod('GET')
            ->setUrlController('App\Http\Controllers\DashboardController')
            ->setUrlAction('index')
            ->setRoles(['admin', 'user'])
            ->setChildrens([])
            ->setEndBlock('test2');
        //->save(parent: 'test');

        if ($this->option('delete')) {
            $routeId = $this->option('id');

            if (!$routeId) {
                $this->info("No se proporcionó un ID. Entrando al modo navegación para seleccionar la ruta a eliminar...");
                $routeId = RouteService::navigate(); // <- obtén el ID navegando
            }

            if ($routeId) {
                FullRoute::find($routeId)
                    ->delete(); // <- elimina la ruta
                $this->info("Ruta con ID {$routeId} eliminada correctamente.");
            } else {
                $this->error("No se pudo obtener un ID de ruta válido.");
            }

            return; // <- finaliza si fue delete
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
            'nueva' => $this->info('Creando nueva ruta...'),
            'mover' => $this->info('Moviendo ruta existente...'),
            'eliminar' => $this->info('Eliminando ruta existente...'),
            'salir' => $this->info('Saliendo...'),
        };
    }
}
