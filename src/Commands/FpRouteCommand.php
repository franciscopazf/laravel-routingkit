<?php

namespace Fp\FullRoute\Commands;

use Fp\FullRoute\Services\FullRouteInteractive;
use Illuminate\Console\Command;
use function Laravel\Prompts\select;

use Fp\FullRoute\Helpers\Navigator;
use Fp\FullRoute\Clases\FullRoute;
use Fp\FullRoute\Services\Navigator\Navigator as NNavigator;



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


        //  dd();
        //dd($transformer->rebuildBlockRecursively( FullRoute::find('DEMOGRAFIA')));
        // dd(FullRoute::all());
        //  dd(FullRoute::all());
        $ranID = rand(1, 1000);
        FullRoute::make($ranID)
            ->setPermission('permission: ' . $ranID)
            ->setTitle('Dashboard' . $ranID)
            ->setDescription('Dashboard de la aplicacion ' . $ranID)
            ->setKeywords('keywords, fp-full-route ' . $ranID)
            ->setIcon('icon ' . $ranID)
            ->setUrl('/dashboard' . $ranID)
            ->setUrlName('dashboard' . $ranID)
            ->setUrlMethod('GET')
            ->setUrlController('App\Http\Controllers\DashboardController')
            ->setPermissions(['admin', 'user'])
            ->setUrlAction('index')
            ->setRoles(['admin', 'user'])
            ->setChildrens([])
            ->setEndBlock($ranID)
        ; // ->save();


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
