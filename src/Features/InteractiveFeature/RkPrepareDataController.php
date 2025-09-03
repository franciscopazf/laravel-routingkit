<?php

namespace Rk\RoutingKit\Features\InteractiveFeature;

use Illuminate\Support\Str;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;


class RkPrepareDataController
{
    public static function make(): self
    {
        return new self();
    }

    public function run(
        ?string $fullPath = null,
        ?string $carpeta = null,
        ?string $nombre = null,
        bool $crearNombreModelo = false
    ): array {
        if ($fullPath !== null) {
            // Resolver valores desde el path completo
            [$carpeta, $nombre] = $this->resolverDesdeFullPath($fullPath);
        }

        if ($carpeta == null) {
            // Flujo interactivo
            $carpeta = RkileBrowser::make()
                ->browseMultipleFolders(
                    config('routingkit.controllers_path')
                );
        }



        if ($nombre == null && !$crearNombreModelo) {
            do {
                $nombre = text('Ingrese el nombre del controlador');

                if (empty(trim($nombre))) {
                    echo "⚠️  El nombre del controlador es requerido. Por favor, ingréselo.\n";
                }
            } while (empty(trim($nombre)));
        }

        $nombre = Str::studly($nombre);
        $nombreArchivo = $nombre . '.php';

        $modelo = RkModelResolver::make()
            ->run();

        if ($crearNombreModelo) {
            // reemplazar {modelo} por el nombre del modelo en la variable $nombre
            $nombreArchivo = str_replace('{modelo}', $modelo['class'], $nombre);
        }

        $namespace = RkNamespaceResolver::make()
            ->getBaseNamespace($carpeta);

        $rutaVista = RkViewResolver::make()
            ->resolveViewObjectFromAnySource($carpeta  . $nombreArchivo);



        //  dd($modelo);

        return  [
            'controller' => [
                'folder' => $carpeta,
                'className' => $nombreArchivo,
                'namespace' => $namespace,
                'fullNamespace' => $namespace . '\\' . $nombreArchivo,
                'path' => $carpeta  . $nombreArchivo,
                'viewName' => $rutaVista['viewName'] ?? '',
            ],
            'view' => $rutaVista,
            'model' => $modelo,
            'carpeta' => $carpeta,
            'nombre' => $nombreArchivo,
        ];
    }

    private function resolverDesdeFullPath(string $fullPath): array
    {
        $carpeta = dirname($fullPath);
        $nombre = basename($fullPath);

        return [$carpeta, $nombre];
    }
}
