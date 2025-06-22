<?php

namespace FPJ\RoutingKit\Features\InteractiveFeature;

use Illuminate\Support\Str;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;


class FPJPrepareDataController
{
    public static function make(): self
    {
        return new self();
    }

    public function run(?string $fullPath = null): array
    {
        if ($fullPath !== null) {
            // Resolver valores desde el path completo
            [$carpeta, $nombre] = $this->resolverDesdeFullPath($fullPath);
        } else {
            // Flujo interactivo
            $carpeta = FPJileBrowser::make()
                ->browseMultipleFolders([
                    base_path('app/Http/Controllers'),
                    base_path('app/Livewire')
                ]);
            do {
                $nombre = text('Ingrese el nombre del controlador');

                if (empty(trim($nombre))) {
                    echo "⚠️  El nombre del controlador es requerido. Por favor, ingréselo.\n";
                }
            } while (empty(trim($nombre)));
            $nombre = Str::studly($nombre);
            $nombreArchivo = $nombre . '.php';
        }

        $namespace = FPJNamespaceResolver::make()
            ->getBaseNamespace($carpeta);

        $rutaVista = FPJViewResolver::make()
            ->resolveViewObjectFromAnySource($carpeta  . $nombreArchivo);

        return  [
            'controller' => [
                'folder' => $carpeta,
                'className' => $nombre,
                'namespace' => $namespace,
                'path' => $carpeta  . $nombreArchivo,
                'viewName' => $rutaVista['viewName'] ?? '',
            ],
            'vista' => $rutaVista,
        ];
    }

    private function resolverDesdeFullPath(string $fullPath): array
    {
        $carpeta = dirname($fullPath);
        $nombre = basename($fullPath);

        return [$carpeta, $nombre];
    }
}
