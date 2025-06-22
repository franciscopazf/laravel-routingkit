<?php

namespace FpF\RoutingKit\Features\InteractiveFeature;

use FpF\RoutingKit\Entities\FpFNavigation;
use FpF\RoutingKit\Features\FileCreatorFeature\FpFileCreator;
use FpF\RoutingKit\Entities\FpFRoute;
use Illuminate\Support\Str;

class FpFCreateSimpleController
{
    public static function make(): self
    {
        return new self();
    }

    public function run(?string $fullPath = null): void
    {
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

        $this->crearRuta($controllerId, $accessPermission, $urlController);
        
        $this->crearNavegacion($controllerId);
    }

    private function prepararDatos(): array
    {
        return FpFPrepareDataController::make()->run();
    }

    private function getControllerStub(string $namespace): string
    {
        $stubDir = __DIR__ . '/stubs';
        return str_contains($namespace, 'Livewire')
            ? file_get_contents($stubDir . '/SimpleControllerLivewire.stub')
            : file_get_contents($stubDir . '/SimpleControllerController.stub');
    }

    private function getViewStub(string $namespace): string
    {
        $stubDir = __DIR__ . '/stubs';
        return str_contains($namespace, 'Livewire')
            ? file_get_contents($stubDir . '/SimpleControllerViewLivewire.stub')
            : file_get_contents($stubDir . '/SimpleControllerView.stub');
    }

    private function renderStub(string $stub, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $stub = str_replace('{{ $' . $key . ' }}', $value, $stub);
        }
        return $stub;
    }

    private function crearArchivo(string $folder, string $fileName, string $content, string $extension): void
    {
        FpFileCreator::make(
            filePath: $folder,
            fileName: $fileName,
            fileContent: $content,
            fileExtension: $extension
        )->createFile();
    }

    private function generarId(string $className): string
    {
        return strtolower($className) . '_' . Str::random(3);
    }

    private function crearRuta(string $id, string $accessPermission, string $urlController): void
    {

        FpFInteractiveNavigator::make(FpFRoute::class)->crear(data: [
            'id' => $id,
            'accessPermission' => $accessPermission,
            'urlController' => $urlController,
            'urlMethod' => 'get',
            'roles' => []
        ]);
    }

    private function crearNavegacion(string $id): void
    {
        FpFInteractiveNavigator::make(FpFNavigation::class)->crear(data: [
            'instanceRouteId' => $id,
            'id' => $id
        ]);
    }
}
