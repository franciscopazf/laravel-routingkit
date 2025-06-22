<?php

namespace FpF\RoutingKit;

use FpF\RoutingKit\Commands\FpFRouteCommand;
use FpF\RoutingKit\Commands\FpFNavigationCommand;
use FpF\RoutingKit\Commands\FpFAccess;
use FpF\RoutingKit\Commands\FpFControllerCommand;
use FpF\RoutingKit\Entities\FpFNavigation;
use Illuminate\Support\ServiceProvider;

class RoutingKitServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('fpf.navigation', function ($app) {
            return FpFNavigation::newQuery();
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
                FpFRouteCommand::class,
                FpFNavigationCommand::class,
                FpFAccess::class,
                FpFControllerCommand::class,
            ]);
        }

        if (file_exists($helperFile = __DIR__ . '/Support/Helpers/helpers.php')) {
            
            require_once $helperFile;
        }

    }
}
