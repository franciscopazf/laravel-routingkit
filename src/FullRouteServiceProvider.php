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
         // Registrar 'fp.navigation' como un singleton en el Service Container.
        // Un singleton significa que Laravel creará UNA ÚNICA instancia de esta clase
        // y la reutilizará cada vez que se solicite 'fp.navigation'.
        // Retornamos FpNavigation::newQuery() porque es la forma en que tu clase
        // se expone como un "Query Builder" o punto de entrada principal.
        $this->app->singleton('fp.navigation', function ($app) {
            return FpNavigation::newQuery();
        });
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

        if (file_exists($helperFile = __DIR__ . '/Support/helpers.php')) {
            
            require_once $helperFile;
        }


        #  $this->loadRoutesFrom(__DIR__ . '/routes/FullRoutesLoader.php');
    }
}
