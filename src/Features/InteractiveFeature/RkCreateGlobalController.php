<?php

namespace Rk\RoutingKit\Features\InteractiveFeature;

use Rk\RoutingKit\Entities\RkNavigation;
use Rk\RoutingKit\Features\FileCreatorFeature\RkileCreator;
use Rk\RoutingKit\Entities\RkRoute;
use Illuminate\Support\Str;

use \Rk\RoutingKit\Contracts\RkCreatorController;
use Illuminate\Support\Facades\View;
use function Laravel\Prompts\info;
use function Laravel\Prompts\comment;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\error;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\select;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;

class RkCreateGlobalController implements RkCreatorController
{
    public array $data = [];

    public static function make(): self
    {
        return new self();
    }

    public function run(?string $fullPath = null): void
    {


        $stubs = config('routingkit.stubs');
        $opciones = array_keys($stubs); // ['weZb', 'admin', etc.]

        $seleccion = select('Selecciona una plantilla para el stub:', $opciones);
        $stubSeleccionado = $stubs[$seleccion]; // Ejemplo: $stubs['web']

        $controladores = $stubSeleccionado['controllers'];


        $carpeta = null;
        $nombre = null;


        // rediderizzar el contenido 
        foreach ($controladores as $controller) {
            $crearNombreModelo = false;

            // si hay nombre por defecto o es string entonces es del tipo
            // {nombremodelo}Create , edit etc
            if (is_string($controller['default_name'])) {
                $nombre = $controller['default_name'];
                $crearNombreModelo = true;
            }

            $data =  RkPrepareDataController::make()->run(
                carpeta: $carpeta,
                nombre: $nombre,
                crearNombreModelo: $crearNombreModelo
            );
            //   dd($data);

            foreach ($controller['views'] as $value) {
                $stubViewPath = $value['stub_path'];
                // crear el contenido de la vista para crear el archivo.
                $contenidoVista = $this->renderFromBlade($stubViewPath, $data);

                // crear el arhivo de la vista
                $this->crearArchivo(
                    $data['view']['folder'],
                    $data['view']['fileName'],
                    $contenidoVista,
                    'blade.php'
                );
            }
            //  dd($data);

            // obtener el stubpath y crear el contenido del controlador
            $stubControllerPath = $controller['stub_path'];
            $contenidoControlador = $this->renderFromBlade($stubControllerPath, $data);

            // crear el archivo del controlador
            $this->crearArchivo(
                $data['controller']['folder'],
                $data['controller']['className'],
                $contenidoControlador,
                'php'
            );

            //  dd($data);

            // validar si se quiere crear una ruta con este archivo 
            // $this->crearRuta($controllerId, $accessPermission, $urlController);
            if ($controller['rk_route']) {
                // preguntar si se quiere crear una ruta para este controlador


                $controllerId = $this->generarId($data['controller']['className']);
                $accessPermission = 'acceder-' . $controllerId;

                $urlController = str_contains($data['controller']['namespace'], 'Livewire')
                    ? $data['controller']['namespace'] . '\\' . $data['controller']['className']
                    : $data['controller']['namespace'] . '\\' . $data['controller']['className'] . '@index';

                $this->crearRuta($controllerId, $accessPermission, $urlController);

                if ($controller['rk_navigation']) {
                    // crear la navegacion
                    $this->crearNavegacion($controllerId);
                }
            }

            $carpeta = $data['carpeta'];
        }
    }


    protected function makeFiles(): bool {}


    protected function renderFromBlade(string $stubPath, ?array $data = null): string
    {
        $filesystem = new Filesystem();

        // 1️⃣ Crear un BladeCompiler "limpio" que compile en storage/framework/views
        $bladeCompiler = new BladeCompiler($filesystem, storage_path('framework/views'));

        // ⚠️ No registramos ComponentTagCompiler, así que <x-...> no se ejecutará

        $resolver = new EngineResolver();
        $resolver->register('blade', function () use ($bladeCompiler, $filesystem) {
            return new CompilerEngine($bladeCompiler, $filesystem);
        });

        $finder = new FileViewFinder($filesystem, [dirname($stubPath)]);

        $factory = new Factory($resolver, $finder, app()['events']);

        // 2️⃣ Renderizar el archivo stub con las variables $data
        return $factory->file($stubPath, [
            'data' => $data ?? $this->data,
        ])->render();
    }

    protected function renderFromStub(string $stubPath, ?array $data = null): string
    {
        $stubContent = file_get_contents($stubPath);
        if ($stubContent === false) {
            throw new \Exception("No se pudo leer el archivo de stub en la ruta: $stubPath");
        }

        // Reemplazar variables en el stub
        if ($data) {
            foreach ($data as $key => $value) {
                $stubContent = str_replace('{{ $' . $key . ' }}', $value, $stubContent);
            }
        }

        return $stubContent;
    }


    protected function crearArchivo(string $folder, string $fileName, string $content, string $extension): void
    {
        RkileCreator::make(
            filePath: $folder,
            fileName: $fileName,
            fileContent: $content,
            fileExtension: $extension
        )->createFile();
    }

    protected function generarId(string $className): string
    {
        return strtolower($className) . '_' . Str::random(3);
    }

    protected function crearRuta(string $id, string $accessPermission, string $urlController): void
    {

        RkInteractiveNavigator::make(RkRoute::class)->crear(data: [
            'id' => $id,
            'accessPermission' => $accessPermission,
            'urlController' => $urlController,
            'urlMethod' => 'get',
        ]);
    }

    protected function crearNavegacion(string $id): void
    {
        RkInteractiveNavigator::make(RkNavigation::class)->crear(data: [
            'instanceRouteId' => $id,
            'id' => $id
        ]);
    }
}
