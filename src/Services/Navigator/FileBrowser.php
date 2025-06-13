<?php

namespace Fp\RoutingKit\Services\Navigator;

use Illuminate\Support\Facades\File;

class FileBrowser
{
    public function __construct()
    {
        // Constructor can be used for dependency injection if needed
    }

    public static function make(): self
    {
        return new self();
    }

    public function browseFolder(string $startPath): string
    {
        $basePath = base_path();
        $currentPath = $startPath;

        while (true) {
            $folders = collect(File::directories($currentPath))
                ->map(fn($dir) => basename($dir))
                ->toArray();

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $currentPath);
            $currentFolderName = basename($currentPath);

            $options = [];

            // Opción para seleccionar esta carpeta
            $options['select'] = "✅ Usar esta carpeta ({$currentFolderName})";

            // Agregar subcarpetas
            foreach ($folders as $folder) {
                $options["dir:{$folder}"] = "📂 {$folder}";
            }

            // Agregar volver atrás si no estamos en la raíz
            if ($currentPath !== $startPath) {
                $options['back'] = '🔙 Volver atrás';
            }

            // Mostrar el prompt con ruta relativa
            $choice = \Laravel\Prompts\select("📁 Estás en: {$relativePath}", $options);

            if ($choice === 'select') {
                return $currentPath;
            }

            if ($choice === 'back') {
                $currentPath = dirname($currentPath);
                continue;
            }

            if (str_starts_with($choice, 'dir:')) {
                $selectedFolder = substr($choice, 4);
                $currentPath = $currentPath . DIRECTORY_SEPARATOR . $selectedFolder;
            }
        }
    }


    public function browsePhpFile(string $startPath): string
    {
        $currentPath = $startPath;

        while (true) {
            $folders = collect(File::directories($currentPath))->map(fn($d) => basename($d))->toArray();
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

            if ($currentPath !== $startPath) {
                $options['back'] = '🔙 Volver atrás';
            }

            $choice = \Laravel\Prompts\select('📁 Selecciona un archivo .php:', $options);

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
}
