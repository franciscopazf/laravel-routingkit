<?php

namespace Fp\FullRoute\Commands;

use Fp\FullRoute\Clases\FullRoute;
use Fp\FullRoute\Services\RouteService;
use Illuminate\Console\Command;


class SaludarCommand extends Command
{
    protected $signature = 'fp:saludar';

    protected $description = 'Saluda desde el paquete FullRoute';

    public function handle()
    {
        RouteService::addRoute(
            route: FullRoute::Make(id: "13")
                ->setParentId('12')
                ->setPermission('admin')
                ->setTitle('Dashboard3')
                ->setDescription('Dashboard de la aplicacion')
                ->setKeywords('dashboard, fp-full-route')
                ->setIcon('fa-solid fa-house')
                ->setUrl('/dashboard3')
                ->setUrlName('dashboard3')
                ->setUrlMethod('GET')
                ->setUrlController('App\Http\Controllers\DashboardController')
                ->setUrlAction('index')
        );
        $this->info('¡Hola desde tu paquete FullRoute!');
    }
}
