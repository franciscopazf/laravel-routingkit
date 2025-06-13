<?php

namespace Fp\RoutingKit\Services\Navigator;

use Fp\RoutingKit\Contracts\FpEntityInterface as RoutingKit;
use Fp\RoutingKit\Services\Navigator\FileBrowser;
use Fp\RoutingKit\Services\Navigator\NamespaceResolver;
use Fp\RoutingKit\Services\Navigator\ClassInspector;
use Illuminate\Support\Collection;
use Fp\RoutingKit\Services\Navigator\TreeNavigator;

use function Laravel\Prompts\select;

class Navigator
{
    public function __construct(
        protected ?FileBrowser $fileBrowser = null,
        protected ?NamespaceResolver $namespaceResolver = null,
        protected ?ClassInspector $inspector = null
    ) {
        $this->fileBrowser = $fileBrowser ?? FileBrowser::make();
        $this->namespaceResolver = $namespaceResolver ?? NamespaceResolver::make();
        $this->inspector = $inspector ?? ClassInspector::make();
    }

    public static function make(
        ?FileBrowser $fileBrowser = null,
        ?NamespaceResolver $namespaceResolver = null,
        ?ClassInspector $inspector = null
    ) {
        return new self($fileBrowser, $namespaceResolver, $inspector);
    }

    public function selectFolderInfo(string $basePath): object
    {
        // dd("Seleccionando carpeta en: " . $basePath);
        $fullBasePath = base_path() . DIRECTORY_SEPARATOR . $basePath;

        $folder = $this->fileBrowser->browseFolder($fullBasePath);
        // dd("Folder seleccionado: " . $folder);
        $namespace = $this->namespaceResolver->getBaseNamespace($folder);


        return (object)[
            'path'      => $folder,
            'namespace' => $namespace,
        ];
    }

    public function selectFileInfo(string $basePath): object
    {
        $fullBasePath = base_path($basePath);
        $namespace = $this->namespaceResolver->getBaseNamespace($fullBasePath);

        $filePath = $this->fileBrowser->browsePhpFile($fullBasePath);
        $fullClass = $this->namespaceResolver->pathToNamespace($fullBasePath, $filePath, $namespace);
        $className = class_basename($fullClass);
        $methods = $this->inspector->getPublicMethods($fullClass);

        return (object)[
            'full'      => $fullClass,
            'namespace' => substr($fullClass, 0, strrpos($fullClass, '\\')),
            'className' => $className,
            'methods'   => $methods,
            'path'      => $filePath,
        ];
    }

    public function selectMethod(string $fullClass): string
    {
        $methods = $this->inspector->getPublicMethods($fullClass);
        return select('🔧 Selecciona un método:', array_combine($methods, $methods));
    }

    public function treeNavigator(
        Collection|array $rutas,
        ?RoutingKit $nodoActual = null,
        array $pila = [],
        ?string $omitId = null
    ): ?string {
        return TreeNavigator::make()
            ->navegar($rutas, $nodoActual, $pila, $omitId);
    }



    public  function getControllerRouteParams(): object
    {
        $basePath = select(
            label: '📂 Selecciona la carpeta del controlador',
            options: config('fproute.controllers_path')

        );

        $class = self::make()
            ->selectFileInfo($basePath)
            ->full;

        $method = $basePath === 'app/Livewire'
            ? 'livewire'
            : self::make()
            ->selectMethod($class);

        return (object) [
            'controller' => $class,
            'action'     => $method,
        ];
    }
}
