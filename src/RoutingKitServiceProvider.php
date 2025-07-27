<?php

namespace Rk\RoutingKit;

use Rk\RoutingKit\Commands\RkRouteCommand;
use Rk\RoutingKit\Commands\RkNavigationCommand;
use Rk\RoutingKit\Commands\RkAccess;
use Rk\RoutingKit\Commands\RkControllerCommand;
use Rk\RoutingKit\Entities\RkNavigation;
use Illuminate\Support\ServiceProvider;

class RoutingKitServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('rk.navigation', function ($app) {
            return RkNavigation::newQuery();
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
                RkRouteCommand::class,
                RkNavigationCommand::class,
                RkAccess::class,
                RkControllerCommand::class,
            ]);
        }

        if (file_exists($helperFile = __DIR__ . '/Support/Helpers/helpers.php')) {
            
            require_once $helperFile;
        }

    }
}
