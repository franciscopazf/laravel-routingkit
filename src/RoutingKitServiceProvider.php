<?php

namespace FPJ\RoutingKit;

use FPJ\RoutingKit\Commands\FPJRouteCommand;
use FPJ\RoutingKit\Commands\FPJNavigationCommand;
use FPJ\RoutingKit\Commands\FPJAccess;
use FPJ\RoutingKit\Commands\FPJControllerCommand;
use FPJ\RoutingKit\Entities\FPJNavigation;
use Illuminate\Support\ServiceProvider;

class RoutingKitServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('fpj.navigation', function ($app) {
            return FPJNavigation::newQuery();
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
                FPJRouteCommand::class,
                FPJNavigationCommand::class,
                FPJAccess::class,
                FPJControllerCommand::class,
            ]);
        }

        if (file_exists($helperFile = __DIR__ . '/Support/Helpers/helpers.php')) {
            
            require_once $helperFile;
        }

    }
}
