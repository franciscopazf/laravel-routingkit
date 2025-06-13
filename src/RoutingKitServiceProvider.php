<?php

namespace Fp\RoutingKit;

use Fp\RoutingKit\Commands\FpRouteCommand;
use Fp\RoutingKit\Commands\FpNavigationCommand;
use Fp\RoutingKit\Commands\FpAcces;

use Fp\RoutingKit\Entities\FpNavigation;
use Illuminate\Support\ServiceProvider;

class RoutingKitServiceProvider extends ServiceProvider
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

        if (file_exists($helperFile = __DIR__ . '/Support/Helpers/helpers.php')) {
            
            require_once $helperFile;
        }


        #  $this->loadRoutesFrom(__DIR__ . '/routes/RoutingKitsLoader.php');
    }
}
