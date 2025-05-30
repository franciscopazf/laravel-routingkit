<?php

namespace Fp\FullRoute\Services\Route\Strategies;

class RouteContentManager
{
    protected string $filePath;
    

    public function __construct(string $filePath = null)
    {
        
        $this->filePath = $filePath ?? config('fproute.routes_fyle_path.web');
    }

    public static function make(string $filePath = null): self
    {
        return new self($filePath);
    }

    public function getContents(): array
    {
        # echo "SE HIZO UNA CARGA DE ARCHIVO \n";
        /** @var array $routes */
        $routes = include $this->filePath;
        return $routes;
    }

    public function getContentsString(): string
    {
        return file_get_contents($this->filePath);
    }

    public function putContents(string $content): void
    {
        file_put_contents($this->filePath, $content);
    }
}
