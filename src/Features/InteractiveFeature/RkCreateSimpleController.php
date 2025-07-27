<?php

namespace Rk\RoutingKit\Features\InteractiveFeature;

use Rk\RoutingKit\Entities\RkNavigation;
use Rk\RoutingKit\Features\FileCreatorFeature\RkileCreator;
use Rk\RoutingKit\Entities\RkRoute;
use Illuminate\Support\Str;

use function Laravel\Prompts\info;
use function Laravel\Prompts\comment;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\error;
use function Laravel\Prompts\progress;


class RkCreateSimpleController
{
    public static function make(): self
    {
        return new self();
    }

    public function run(?string $fullPath = null): void
    {


        // Paso 1: Preparar datos y crear archivos
        info('Paso 1: Preparando datos y generando archivos...');
        $data = $this->prepararDatos();

        $controllerStub = $this->getControllerStub($data['controller']['namespace']);
        $controllerContent = $this->renderStub($controllerStub, $data['controller']);

        $viewStub = $this->getViewStub($data['controller']['namespace']);
        $viewContent = $this->renderStub($viewStub, $data['controller']);

        $this->crearArchivo(
            $fullPath ?? $data['controller']['folder'],
            $data['controller']['className'],
            $controllerContent,
            'php'
        );

        $this->crearArchivo(
            $data['vista']['folder'],
            $data['vista']['fileName'],
            $viewContent,
            'blade.php'
        );

        $controllerId = $this->generarId($data['controller']['className']);
        $accessPermission = 'acceder-' . $controllerId;

        $urlController = str_contains($data['controller']['namespace'], 'Livewire')
            ? $data['controller']['namespace'] . '\\' . $data['controller']['className']
            : $data['controller']['namespace'] . '\\' . $data['controller']['className'] . '@index';


        // Paso 2: Crear ruta
        info('Paso 2: Creando ruta...');
        $this->crearRuta($controllerId, $accessPermission, $urlController);


        // Paso 3: Crear navegación
        info('Paso 3: Creando navegación...');
        $this->crearNavegacion($controllerId);
    }

    protected function prepararDatos(): array
    {
        return RkPrepareDataController::make()->run();
    }

    protected function getControllerStub(string $namespace): string
    {
        $stubDir = __DIR__ . '/stubs';
        return str_contains($namespace, 'Livewire')
            ? file_get_contents($stubDir . '/SimpleControllerLivewire.stub')
            : file_get_contents($stubDir . '/SimpleControllerController.stub');
    }

    protected function getViewStub(string $namespace): string
    {
        $stubDir = __DIR__ . '/stubs';
        return str_contains($namespace, 'Livewire')
            ? file_get_contents($stubDir . '/SimpleControllerViewLivewire.stub')
            : file_get_contents($stubDir . '/SimpleControllerView.stub');
    }

    protected function renderStub(string $stub, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $stub = str_replace('{{ $' . $key . ' }}', $value, $stub);
        }
        return $stub;
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
