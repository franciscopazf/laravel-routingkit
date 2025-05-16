<?php

namespace Fp\FullRoute;

use Illuminate\Support\ServiceProvider;
use Fp\FullRoute\Commands\SaludarCommand;

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
                SaludarCommand::class,
            ]);
        }

        $this->loadRoutesFrom(__DIR__ . '/routes/FullRoutesLoader.php');
    }
}
