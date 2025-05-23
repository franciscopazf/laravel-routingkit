<?php

namespace Fp\FullRoute\Services;

use Fp\FullRoute\Clases\FullRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Fp\FullRoute\Services\RouteContentManager;

/*
* ESTA CLASE, RECIVE COMO PARAMETRO UNA COLLECTION DE FULLROUTE
* Y "FORMATEA LA COLLECCION IDENTANDOLA CORRECTAMENTE
* ADEMAS DE "PARSEARLA" A UN ARRAY PLANO O A FORMATO DE ARBOL
*
*/

class Transformer
{

    public function __construct(
        private RouteContentManager $routeContentManager,
        private Collection $fullRouteCollection,
    ) {}

    public static function make(
        RouteContentManager $routeContentManager,
        Collection $fullRouteCollection
    ): self {
        return new self($routeContentManager, $fullRouteCollection);
    }

    // esta funcion se encarga de escribir el contenido de la coleccion 
    // entonces cuando se llame a esta funcion ya se debio validar
    // se debio mover actualizar o eliminar la coleccion de rutas
    // es decir la responsabilidad de esta clase como tal es solamente escribir
    // la coleccion de rutas en el archivo dado nada mas.
    public function reWriteContent(): void
    {
        $newContent  = $this->prepareContent();
        $this->routeContentManager->putContents($newContent);
    }

    public function prepareContent(): string
    {
        $header = $this->getHeaderBlock();
        $content = $this->getContentBlock();
        $footer = $this->getFooterBlock();

        $content = $header . "\n" . $content . "\n" . $footer;
        return $content;
    }

    private function getContentBlock(): string
    {
        $content = $this->fullRouteCollection
            ->map(fn(FullRoute $route) => $this->rebuildBlockRecursively($route))
            ->join(",\n");

        return $content;
    }

    private function getFooterBlock(): string
    {
        return "\n];";
    }

    public function rebuildBlockRecursively(FullRoute $route): string
    {
        $block = $this->getBlock($route);

        $newChildrenBlocks = collect($route->getChildrens())
            ->map(fn(FullRoute $child) => $this->rebuildBlockRecursively($child))
            ->join(",\n");

        $pattern = $this->getChildrenPattern($route->id);

        $blockUpdated = preg_replace_callback(
            $this->getChildrenPattern($route->id),
            function () use ($newChildrenBlocks, $route) {
                if (trim($newChildrenBlocks) === '') {
                    return "->setChildrens([])"
                        . "\n        ->setEndBlock('{$route->id}')";
                }

                return "->setChildrens([" . $this->indentBlock($newChildrenBlocks) . "\n        ])"
                    . "\n        ->setEndBlock('{$route->id}')";
            },
            $block
        );

        return $blockUpdated;
    }


    private function indentBlock(string $block, int $level = 2): string
    {
        #echo "LEVEL: " . $level . "\n";
        $indent = str_repeat("    ", $level);
        try {
            return collect(explode("\n", $block))
                ->map(fn($line) => $indent . $line)
                ->join("\n");
        } catch (\Throwable $th) {
            # echo "Error al indentar el bloque: " . $th->getMessage();
            return $block; // Devuelve el original si algo falla
        }
    }

    public function getBlock(string|FullRoute $route): string
    {

        $file = $this->routeContentManager->getContentsString();
        $fromRouteId = $route instanceof FullRoute ? $route->getId() : $route;

        $pattern = $this->getPattern($fromRouteId);

        if (!preg_match($pattern, $file, $matches)) {
            throw new \Exception("No se encontró la ruta con ID {$fromRouteId}");
        }
        $newBlock = "\n\n    " . $matches[0];
        # echo "\nBloque Encontrado: " . $newBlock;
        return $newBlock;
    }


    private function getHeaderBlock(): string
    {
        $file = $this->routeContentManager->getContentsString();
        if (!preg_match($this->getHeaderPatterns(), $file, $matches)) {
            throw new \Exception("No se encontró el bloque de encabezado");
        }
        return $matches[0];
    }

    private function getHeaderPatterns(): string
    {
        return $pattern = '/<\?php.*?return\s*\[/sx';
    }

    private function getChildrenPattern(string $routeId): string
    {
        return '/
        ->setChildrens\((.*?)\)                     # Grupo 1: contenido dentro del setChildrens(...)
        \s*                                         # posibles espacios o saltos de línea
        ->setEndBlock\(\s*[\'"]' . preg_quote($routeId, '/') . '[\'"]\s*\)   # ->setEndBlock("ID")
        (,)?                                        # Grupo 2: coma final opcional
    /sx'; // ⚠️ 's' para que el punto incluya saltos de línea, 'x' para comentarios legibles
    }



    // funcion que recive un parametro un string y retorna el patron que permite buscar rutas
    // en el archivo de rutas.
    private function getPattern(string $routeId): string
    {
        return $pattern = '/
           #   (,)?\s*                                             # Grupo 1: coma inicial si existe
            FullRoute::make\(\s*[\'"]' . preg_quote($routeId, '/') . '[\'"]\s*\)  # FullRoute::make()
            .*?                                                # cualquier cosa entre medio (lazy)
            ->setEndBlock\(\s*[\'"]' . preg_quote($routeId, '/') . '[\'"]\s*\)    # ->setEndBlock()
            (,)?                                               # Grupo 2: coma final si existe
            (?=(\r?|\r))                                     # Lookahead: conserva salto de línea (no se elimina)
        /sx';
    }
}
