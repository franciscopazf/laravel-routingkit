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
        FullRoute::Make(id: "dashboard3")
            ->setParentId('dashboard')
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
            ->setRoles(['admin', 'user'])
            ->setChildrens([])
            ->save();


        $this->info('¡Hola desde tu paquete FullRoute!');
    }
}
