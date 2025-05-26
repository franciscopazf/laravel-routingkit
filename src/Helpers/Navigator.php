<?php

namespace Fp\FullRoute\Helpers;

use Illuminate\Support\Facades\File;
use function Laravel\Prompts\select;
use Illuminate\Support\Collection;
use Fp\FullRoute\Clases\FullRoute;

use ReflectionClass;

class Navigator
{
    protected string $fullBasePath;
    protected string $basePath;
    protected string $baseNamespace;
    protected ?object $selectedFileInfo = null;

    public function __construct(string $basePath = null)
    {
        $this->basePath = $basePath ?? 'app';
        $this->fullBasePath = base_path($this->basePath);
        $this->baseNamespace = $this->getBaseNamespace();
    }

    public function __call($metodo, $args)
    {
        // Intercepta llamadas no estáticas
        return $this->$metodo(...$args);
    }

    public static function make(string $basePath = null): static
    {
        return new static($basePath);
    }

    protected function getBaseNamespace(): string
    {
        $appPath = realpath(app_path());
        $base = realpath($this->fullBasePath);

        if ($appPath && $base && str_starts_with($base, $appPath)) {
            $relative = trim(str_replace($appPath, '', $base), DIRECTORY_SEPARATOR);
            return trim('App\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relative), '\\');
        }

        return 'App';
    }

    public function selectFolderInfo(string $baseNamespace = null): object
    {
        $folderPath = $this->browseAndSelectFolder();
        $baseNamespace = $baseNamespace ?? $this->baseNamespace;

        $relative = str_replace($this->fullBasePath . '/', '', $folderPath);
        $namespace = trim($baseNamespace . '\\' . str_replace('/', '\\', $relative), '\\');

        return (object) [
            'path'      => $folderPath,
            'namespace' => $namespace,
        ];
    }

    public function selectFileInfo(string $baseNamespace = null): object
    {
        $filePath = $this->browseAndSelectPhpFile();
        $baseNamespace = $baseNamespace ?? $this->baseNamespace;

        $fullNamespace = $this->pathToClassNamespace($filePath, $baseNamespace);
        $className = class_basename($fullNamespace);

        $info = (object) [
            'full'      => $fullNamespace,
            'namespace' => substr($fullNamespace, 0, strrpos($fullNamespace, '\\')),
            'className' => $className,
            'methods'   => $this->getPublicMethods($fullNamespace),
            'path'      => $filePath,
        ];

        $this->selectedFileInfo = $info;

        return $info;
    }

    public function selectFunction(?string $fullNamespace = null): string
    {
        $fullNamespace ??= $this->selectedFileInfo?->full;

        if (!$fullNamespace) {
            throw new \RuntimeException("No se ha seleccionado ninguna clase aún.");
        }

        $methods = $this->getPublicMethods($fullNamespace);

        if (empty($methods)) {
            throw new \RuntimeException("La clase {$fullNamespace} no tiene métodos públicos.");
        }

        return select('🔧 Selecciona un método:', array_combine($methods, $methods));
    }

    // Métodos privados
    private function browseAndSelectPhpFile(): string
    {
        $currentPath = $this->fullBasePath;

        while (true) {
            $folders = collect(File::directories($currentPath))
                ->map(fn($dir) => basename($dir))
                ->toArray();

            $phpFiles = collect(File::files($currentPath))
                ->filter(fn($file) => $file->getExtension() === 'php')
                ->map(fn($file) => $file->getFilename())
                ->toArray();

            $options = [];

            foreach ($phpFiles as $file) {
                $options["file:{$file}"] = "📄 {$file}";
            }

            foreach ($folders as $folder) {
                $options["dir:{$folder}"] = "📂 {$folder}";
            }

            if ($currentPath !== $this->fullBasePath) {
                $options['back'] = '🔙 Volver atrás';
            }

            $choice = select('📁 Selecciona un archivo .php:', $options);

            if (str_starts_with($choice, 'file:')) {
                return $currentPath . '/' . substr($choice, 5);
            }

            if ($choice === 'back') {
                $currentPath = dirname($currentPath);
            } elseif (str_starts_with($choice, 'dir:')) {
                $currentPath .= '/' . substr($choice, 4);
            }
        }
    }

    private function browseAndSelectFolder(): string
    {
        $currentPath = $this->fullBasePath;

        while (true) {
            $folders = collect(File::directories($currentPath))
                ->map(fn($dir) => basename($dir))
                ->toArray();

            $options = [];

            foreach ($folders as $folder) {
                $options["dir:{$folder}"] = "📂 {$folder}";
            }

            if ($currentPath !== $this->fullBasePath) {
                $options['back'] = '🔙 Volver atrás';
            }

            $choice = select('📁 Selecciona una carpeta:', $options);

            if ($choice === 'back') {
                $currentPath = dirname($currentPath);
            } elseif (str_starts_with($choice, 'dir:')) {
                $currentPath .= '/' . substr($choice, 4);
            } else {
                return $currentPath;
            }

            if (empty(File::directories($currentPath))) {
                return $currentPath;
            }
        }
    }

    private function pathToClassNamespace(string $filePath, string $baseNamespace): string
    {
        $relative = str_replace($this->fullBasePath . '/', '', $filePath);
        $class = str_replace(['/', '.php'], ['\\', ''], $relative);
        return trim($baseNamespace . '\\' . $class, '\\');
    }

    private function getPublicMethods(string $fullClass): array
    {
        if (!class_exists($fullClass)) {
            throw new \RuntimeException("La clase {$fullClass} no existe.");
        }

        $ref = new ReflectionClass($fullClass);

        return collect($ref->getMethods(\ReflectionMethod::IS_PUBLIC))
            ->filter(fn($method) => $method->class === $ref->getName() && !$this->isMagicMethod($method->name))
            ->map(fn($method) => $method->name)
            ->values()
            ->toArray();
    }

    private function isMagicMethod(string $methodName): bool
    {
        return str_starts_with($methodName, '__');
    }

    public static function getControllerRouteParams() : object
    {
        $basePath = select(
            label: '📂 Selecciona la carpeta del controlador',
            options: config('fproute.controllers_path')
                
        );

        $navigator = self::make($basePath);
        $class = $navigator->selectFileInfo();

        $method = $basePath === 'app/Livewire'
            ? 'livewire'
            : $navigator->selectFunction();

        return (object) [
            'controller' => $class->full,
            'action'     => $method,
        ];
    }



    /**
     * Navega interactivamente por una colección de rutas FullRoute.
     *
     * @param Collection|array $rutas Colección o arreglo de FullRoute
     * @param FullRoute|null $nodoActual Nodo actual para mostrar sus hijos
     * @param array $pila Pila para retroceder en la navegación
     * @param string|null $omitId ID de la ruta que se debe omitir de la navegación
     * @return string Id de la ruta seleccionada
     */
    public static function navegar(
        Collection|array $rutas,
        ?FullRoute $nodoActual = null,
        array $pila = [],
        ?string $omitId = null
    ): ?string {

        // Asegurarse de que $rutas es una colección
        $rutas = is_array($rutas) ? collect($rutas) : $rutas;
        $opciones = [];
        if ($nodoActual) {
            // Obtener hijos como colección (compatibilidad array o colección)
            $hijos = $nodoActual->getChildrens();
            $hijos = is_array($hijos) ? collect($hijos) : $hijos;

            foreach ($hijos as $child) {
                if ($child->id === $omitId) continue;
                $opciones[$child->id] = '📁 ' . $child->title;
            }

            $opciones['__seleccionar__'] = '✅ Seleccionar esta ruta';

            if (!empty($pila)) {
                $opciones['__atras__'] = '🔙 Regresar';
            }
        } else {
            // Mostrar rutas raíz
            foreach ($rutas as $ruta) {
                if ($ruta->id === $omitId) continue;
                $opciones[$ruta->id] = '📁 ' . $ruta->title;
            }

            $opciones['__seleccionar__'] = '✅ Seleccionar una ruta raíz';
            $opciones['__salir__'] = '🚪 Salir';
        }

        // Construir breadcrumb
        $breadcrumb = collect($pila)
            ->pluck('title')
            ->push(optional($nodoActual)->title)
            ->filter()
            ->implode(' > ');

        $seleccion = select(
            label: $breadcrumb ? "Ruta actual: {$breadcrumb}" : "Selecciona una ruta raíz",
            options: $opciones
        );

        return match ($seleccion) {
            '__salir__' => exit("🚪 Saliendo del navegador de rutas.\n"),
            '__seleccionar__' => $nodoActual?->id ?? null,
            '__atras__' => self::navegar($rutas, array_pop($pila), $pila, $omitId),
            default => self::navegar(
                $rutas,
                // Buscar siguiente nodo en hijos o rutas raíz
                ($nodoActual
                    ? collect($nodoActual->getChildrens())
                    : $rutas
                )->firstWhere(fn($r) => $r->id === $seleccion),
                array_merge($pila, [$nodoActual]),
                $omitId
            ),
        };
    }
}

