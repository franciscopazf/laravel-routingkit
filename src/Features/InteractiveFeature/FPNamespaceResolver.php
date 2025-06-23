<?php

namespace FP\RoutingKit\Features\InteractiveFeature;

class FPNamespaceResolver
{
    public function __construct()
    {
        // Constructor can be used for dependency injection if needed
    }

    public static function make(): self
    {
        return new self();
    }

    public function getBaseNamespace(?string $fullPath = null): string
    {
        //dd("Resolviendo namespace para el path: {$fullPath}");
        $basePath = base_path();
        $fullPath = $fullPath ?? $basePath;

        // Normaliza ambos caminos
        $basePath = realpath($basePath);
        $fullPath = realpath($fullPath);
       // dd("Base path: {$basePath}, Full path: {$fullPath}");

        if (!$basePath || !$fullPath || !str_starts_with($fullPath, $basePath)) {
            return '';
        }

        $relativePath = trim(str_replace($basePath, '', $fullPath), DIRECTORY_SEPARATOR);

        // Convierte a formato de namespace y capitaliza cada parte
        $segments = $relativePath ? explode(DIRECTORY_SEPARATOR, $relativePath) : [];
        $capitalized = array_map(fn($segment) => ucfirst($segment), $segments);

        $namespace = ($capitalized ?  implode('\\', $capitalized) : '');

        return trim($namespace, '\\');
    }


    public function pathToNamespace(string $basePath, string $filePath, string $baseNamespace): string
    {
        $relative = str_replace($basePath . '/', '', $filePath);
        $class = str_replace(['/', '.php'], ['\\', ''], $relative);
        return trim($baseNamespace . '\\' . $class, '\\');
    }
}
