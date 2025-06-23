<?php

namespace FP\RoutingKit;

use FP\RoutingKit\Commands\FPRouteCommand;
use FP\RoutingKit\Commands\FPNavigationCommand;
use FP\RoutingKit\Commands\FPAccess;
use FP\RoutingKit\Commands\FPControllerCommand;
use FP\RoutingKit\Entities\FPNavigation;
use Illuminate\Support\ServiceProvider;

class RoutingKitServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('fp.navigation', function ($app) {
            return FPNavigation::newQuery();
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
                FPRouteCommand::class,
                FPNavigationCommand::class,
                FPAccess::class,
                FPControllerCommand::class,
            ]);
        }

        if (file_exists($helperFile = __DIR__ . '/Support/Helpers/helpers.php')) {
            
            require_once $helperFile;
        }

    }
}
