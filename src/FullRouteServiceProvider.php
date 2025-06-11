<?php

namespace Fp\FullRoute;

use Illuminate\Support\ServiceProvider;
use Fp\FullRoute\Commands\FpRouteCommand;
use Fp\FullRoute\Commands\FpNavigationCommand;
use Fp\FullRoute\Commands\FpAcces;
use Fp\FullRoute\Entities\FpNavigation;

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
                FpNavigationCommand::class,
                FpAcces::class,
            ]);
        }

        #  $this->loadRoutesFrom(__DIR__ . '/routes/FullRoutesLoader.php');
    }
}
