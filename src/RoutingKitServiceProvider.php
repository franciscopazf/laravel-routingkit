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

        $this->publishes([
            __DIR__.'/../config/routingkit.php' => config_path('routingkit.php'),
        ], 'routingkit-config'); // La etiqueta 'routingkit-config' permite publicar solo la config

        $this->publishes([
            __DIR__.'/../routingkit' => base_path('routingkit'),
        ], 'routingkit-assets'); // La etiqueta 'routingkit-assets' permite publicar solo la carpeta

        $this->publishes([
            __DIR__.'/../config/routingkit.php' => config_path('routingkit.php'),
            __DIR__.'/../routingkit'            => base_path('routingkit'),
        ], 'routingkit-full'); // La etiqueta 'routingkit-full' permite publicar todo el contenido del paquete

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

    }
}
