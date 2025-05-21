<?php

namespace Fp\FullRoute\Helpers;

use Illuminate\Support\Facades\File;
use ReflectionClass;
use function Laravel\Prompts\select;

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
}
