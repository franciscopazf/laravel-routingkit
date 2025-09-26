<?php

namespace Rk\RoutingKit;

use Rk\RoutingKit\Commands\RkRouteCommand;
use Rk\RoutingKit\Commands\RkNavigationCommand;
use Rk\RoutingKit\Commands\RkAccess;
use Rk\RoutingKit\Commands\RkControllerCommand;
use Rk\RoutingKit\Entities\RkNavigation;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;


use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Illuminate\Filesystem\Filesystem;

class RoutingKitServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('rk.navigation', function ($app) {
            return RkNavigation::newQuery();
        });
    }



    public function registerViews()
    {
        // Registrar las vistas normales del paquete
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'routingkit');
    }

    public function registerBladeComponents()
    {
        $basePath = resource_path('views/rk'); // Carpeta principal de componentes

        if (is_dir($basePath)) {
            // Recorremos todas las carpetas dentro de rk
            foreach (glob($basePath . '/*', GLOB_ONLYDIR) as $dir) {
                $folder = basename($dir); // nombre de la carpeta, ej: "v1", "ui", etc.

                // Registramos un namespace para cada carpeta
                Blade::anonymousComponentPath($dir, "rk.$folder");
              //  Blade::anonymousComponentPath($dir, "$folder");
            }
        }

        // Opcional: también registramos la carpeta base para <x-rk::h />
        Blade::anonymousComponentPath($basePath, 'rk');
    }

    

    public function boot()
    {
        $this->registerBladeComponents();
        $this->registerViews();
        // Carga vistas normales también (por si usas @include('rk::...'))


        $this->publishes([
            __DIR__ . '/../resources/config/routingkit.php' => config_path('routingkit.php'),
        ], 'routingkit-config'); // La etiqueta 'routingkit-config' permite publicar solo la config

        $this->publishes([
            __DIR__ . '/../resources/routingkit' => base_path('routingkit'),
        ], 'routingkit-assets'); // La etiqueta 'routingkit-assets' permite publicar solo la carpeta de routingkit

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/rk'),
            __DIR__ . '/../resources/public/js' => public_path('js/rk'),
            __DIR__ . '/../resources/public/css' => public_path('css/rk'),
        ], 'routingkit-views'); // La etiqueta 'routingkit-views' permite publicar solo las vistas de routingkit

        $this->publishes([
            __DIR__ . '/../resources/config/routingkit.php' => config_path('routingkit.php'),
            __DIR__ . '/../resources/routingkit'            => base_path('routingkit'),
            __DIR__ . '/../resources/views'                 => resource_path('views/rk'),
        ], 'routingkit-full'); // La etiqueta 'routingkit-full' permite publicar todo el contenido del paquete


        //  if ($this->app->runningInConsole()) {
        $this->commands([
            RkRouteCommand::class,
            RkNavigationCommand::class,
            RkAccess::class,
            RkControllerCommand::class,
        ]);
        // }

        if (file_exists($helperFile = __DIR__ . '/Support/Helpers/helpers.php')) {

            require_once $helperFile;
        }
    }
}
