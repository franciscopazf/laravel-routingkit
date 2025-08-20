<?php

namespace Rk\RoutingKit\Features\InteractiveFeature;

class RkViewResolver
{
    public static function make(): self
    {
        return new self();
    }

    public function resolveViewFromAnySource(string $fullPath): ?string
    {
        $appPath = realpath(app_path());

        if (!$fullPath || !$appPath || !str_starts_with($fullPath, $appPath)) {
            return null;
        }

        $viewBasePath = base_path('resources/views');
        $relativePath = trim(str_replace($appPath, '', $fullPath), DIRECTORY_SEPARATOR);

        if (str_starts_with($relativePath, 'Livewire' . DIRECTORY_SEPARATOR)) {
            $targetViewFolder = 'livewire';
            $relativeViewPath = substr($relativePath, strlen('Livewire' . DIRECTORY_SEPARATOR));

            // ✅ Convertir CamelCase a kebab-case (para carpetas y archivos)
            $relativeViewPath = $this->convertCamelToKebabPath($relativeViewPath);
        } elseif (str_starts_with($relativePath, 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR)) {
            $targetViewFolder = 'controllers';
            $relativeViewPath = substr($relativePath, strlen('Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR));

            // ✅ Para controllers mantenemos normal en minúsculas
            $relativeViewPath = strtolower($relativeViewPath);
        } else {
            return null;
        }

        // ✅ Reemplazar extensión .php por .blade.php
        $relativeViewPath = preg_replace('/\.php$/', '.blade.php', $relativeViewPath);

        return $viewBasePath . DIRECTORY_SEPARATOR . $targetViewFolder . DIRECTORY_SEPARATOR . $relativeViewPath;
    }

    public function getViewName(string $fullViewPath): ?string
    {
        $resourcesPath = realpath(resource_path('views'));
        if (!$fullViewPath || !$resourcesPath || !str_starts_with($fullViewPath, $resourcesPath)) {
            return null;
        }

        $relativePath = trim(str_replace($resourcesPath, '', $fullViewPath), DIRECTORY_SEPARATOR);
        $relativePath = preg_replace('/\.blade\.php$/', '', $relativePath);
        return str_replace(DIRECTORY_SEPARATOR, '.', $relativePath);
    }

    public function getViewFolder(string $fullViewPath): ?string
    {
        return dirname($fullViewPath) . DIRECTORY_SEPARATOR;
    }

    public function getFileNameWithoutExtension(string $fullViewPath): ?string
    {
        if (!$fullViewPath) {
            return null;
        }

        $basename = basename($fullViewPath); 
        $basename = preg_replace('/\.blade\.php$/', '', $basename); 

        return $basename;
    }

    public function resolveViewObjectFromAnySource(string $fullPath): ?array
    {
        $resolvedPath = $this->resolveViewFromAnySource($fullPath);

        $viewName = $this->getViewName($resolvedPath);
        $viewFolder = $this->getViewFolder($resolvedPath);
        $fileName = $this->getFileNameWithoutExtension($resolvedPath);

        return [
            'path' => $resolvedPath,
            'viewName' => $viewName,
            'folder' => $viewFolder,
            'fileName' => $fileName,
        ];
    }

    /**
     * ✅ Convierte un path con posibles carpetas y nombres en CamelCase a kebab-case
     * Ejemplo: AdminPanel/AdminGeneral.php → admin-panel/admin-general.php
     */
    private function convertCamelToKebabPath(string $path): string
    {
        $segments = explode(DIRECTORY_SEPARATOR, $path);

        $convertedSegments = array_map(function ($segment) {
            return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $segment));
        }, $segments);

        return implode(DIRECTORY_SEPARATOR, $convertedSegments);
    }
}
