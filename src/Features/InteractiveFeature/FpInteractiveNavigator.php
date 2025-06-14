<?php

namespace Fp\RoutingKit\Features\InteractiveFeature;

use Fp\RoutingKit\Contracts\FpEntityInterface;
use Fp\RoutingKit\Features\InteractiveFeature\FpFileBrowser;
use Fp\RoutingKit\Features\InteractiveFeature\FpNamespaceResolver;
use Fp\RoutingKit\Features\InteractiveFeature\FpClassInspector;
use Fp\RoutingKit\Features\InteractiveFeature\TreeNavigator;

use Illuminate\Support\Collection;
use function Laravel\Prompts\select;



class FpInteractiveNavigator
{ 
     public function __construct(
        protected ?FpFileBrowser $FpFileBrowser = null,
        protected ?FpNamespaceResolver $FpNamespaceResolver = null,
        protected ?FpClassInspector $inspector = null
    ) {
        $this->FpFileBrowser = $FpFileBrowser ?? FpFileBrowser::make();
        $this->FpNamespaceResolver = $FpNamespaceResolver ?? FpNamespaceResolver::make();
        $this->inspector = $inspector ?? FpClassInspector::make();
    }

    public static function make(
        ?FpFileBrowser $FpFileBrowser = null,
        ?FpNamespaceResolver $FpNamespaceResolver = null,
        ?FpClassInspector $inspector = null
    ) {
        return new self($FpFileBrowser, $FpNamespaceResolver, $inspector);
    }

    public function selectFolderInfo(string $basePath): object
    {
        // dd("Seleccionando carpeta en: " . $basePath);
        $fullBasePath = base_path() . DIRECTORY_SEPARATOR . $basePath;

        $folder = $this->FpFileBrowser->browseFolder($fullBasePath);
        // dd("Folder seleccionado: " . $folder);
        $namespace = $this->FpNamespaceResolver->getBaseNamespace($folder);


        return (object)[
            'path'      => $folder,
            'namespace' => $namespace,
        ];
    }

    public function selectFileInfo(string $basePath): object
    {
        $fullBasePath = base_path($basePath);
        $namespace = $this->FpNamespaceResolver->getBaseNamespace($fullBasePath);

        $filePath = $this->FpFileBrowser->browsePhpFile($fullBasePath);
        $fullClass = $this->FpNamespaceResolver->pathToNamespace($fullBasePath, $filePath, $namespace);
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
        ?FpEntityInterface $nodoActual = null,
        array $pila = [],
        ?string $omitId = null
    ): ?string {
        return FpTreeNavigator::make()
            ->navegar($rutas, $nodoActual, $pila, $omitId);
    }



    public  function getControllerRouteParams(): object
    {
        $basePath = select(
            label: '📂 Selecciona la carpeta del controlador',
            options: config('routingkit.controllers_path')

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