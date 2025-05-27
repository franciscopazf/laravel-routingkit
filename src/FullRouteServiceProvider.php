<?php

namespace Fp\FullRoute;

use Illuminate\Support\ServiceProvider;
use Fp\FullRoute\Commands\FpRouteCommand;
use Fp\FullRoute\Commands\FpChangeSupportFile; // Este comando ha sido eliminado

class FullRouteServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Aquí se podrían registrar bindings si querés
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                FpRouteCommand::class,
                FpChangeSupportFile::class, // Este comando ha sido eliminado
            ]);
        }

        #  $this->loadRoutesFrom(__DIR__ . '/routes/FullRoutesLoader.php');
    }
}
