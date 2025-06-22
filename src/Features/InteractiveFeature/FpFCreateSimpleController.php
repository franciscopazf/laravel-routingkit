<?php

namespace FpF\RoutingKit\Features\InteractiveFeature;

use FpF\RoutingKit\Features\FileCreatorFeature\FpFileCreator;
use Illuminate\Support\Facades\Artisan;


class FpFCreateSimpleController
{

    public static function make(): self
    {
        return new self();
    }

    public function run(?string $fullPath = null): void
    {
        $data = FpFPrepareDataController::make()
            ->run();
        //   dd($data);

        $namespace = $data->controller['namespace'] ?? '';

        // Elegir el stub según el tipo
        $stubDir = __DIR__ . '/stubs';

        if (str_contains($namespace, 'Livewire')) {
            $stubPath = $stubDir . '/SimpleControllerLivewire.stub';
        } else {
            $stubPath = $stubDir . '/SimpleControllerController.stub';
        }
        // cargar el stub
        $stub = file_get_contents($stubPath);

        // Reemplazar los marcadores de posición en el stub
        // recorrer las variables del controller y reemplazar los marcadores de posición
        foreach ($data->controller as $key => $value) {
            $stub = str_replace('{{ ' . '$' . $key . ' }}', $value, $stub);
        }

        // crear el archivo de controlador
        FpFileCreator::make(
            filePath: $fullPath ?? $data->controller['folder'],
            fileName: $data->controller['className'],
            fileContent: $stub,
            fileExtension: 'php'
        )
        ->createFile();

        $viewStub = $stubDir . '/SimpleControllerView.stub';

        // reemplazar los marcadores de posición en el stub de la vista
        $stub = file_get_contents($viewStub);
        foreach ($data->controller as $key => $value) {
            $stub = str_replace('{{ ' . '$' . $key . ' }}', $value, $stub);
        }

        // crear el archivo de vista
        FpFileCreator::make(
            filePath: $data->vista['folder'],
            fileName: $data->vista['fileName'],
            fileContent: $stub,
            fileExtension: 'blade.php'
        )
        ->createFile();

        // invocar al comando para crear la ruta
      //  Artisan::call('fpf:route');

    }
}
