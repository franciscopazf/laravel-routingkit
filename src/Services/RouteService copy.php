<?php

namespace Fp\FullRoute\Services;

use Fp\FullRoute\Clases\FullRoute;
use Fp\FullRoute\Services\RouteValidationService;
use Fp\FullRoute\Services\RouteFileManager;

use Illuminate\Support\Collection;

class RouteService
{
    protected RouteFileManager $fileManager;

    public function __construct(RouteFileManager $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    public static function make(RouteFileManager $fileManager): self
    {
        return new self($fileManager);
    }

    public function addRoute(FullRoute $route): void
    {
        RouteValidationService::validateInsertRoute($route);
        $bloque = self::buildFullRouteString($route);
        $parentRoute = $route->getParentRoute();
        $this->insertRouteContent($parentRoute, $bloque);
    }

    public function getAllRoutes(): Collection
    {
        $routes = $this->fileManager
            ->getContents();
        $setParentRefs = function ($node, $parent = null) use (&$setParentRefs) {
            if ($parent !== null) {
                $node->setParentId($parent->getId());
                $node->setParent($parent);
            }
            foreach ($node->getChildrens() as $child) {
                $setParentRefs($child, $node);
            }
            return $node;
        };


        return collect($routes)->map(fn($route) => $setParentRefs($route));
    }

    public function findRoute(string $routeId): ?FullRoute
    {
        return $this->getAllFlattenedRoutes($this->getAllRoutes())
            ->first(fn(FullRoute $route) => $route->getId() === $routeId);
    }

    public function moveRoute(FullRoute $fromRoute, FullRoute $toRoute): void
    {
        RouteValidationService::validateMoveRoute($fromRoute);

        $file = $this->fileManager->getContentsString();
        $fromRouteId = $fromRoute->getId();

        $pattern = '/FullRoute::make\(\s*[\'"]' . preg_quote($fromRouteId, '/') .
            '[\'"]\)(.*?)?->setEndBlock\(\s*[\'"]' . preg_quote($fromRouteId, '/') . '[\'"]\)/s';

        if (!preg_match($pattern, $file, $matches)) {
            throw new \Exception("No se encontró la ruta con ID {$fromRouteId}");
        }

        $bloque = $matches[0];
        $this->removeRoute($fromRouteId);
        $this->insertRouteContent($toRoute, $bloque);
    }

    public function removeRoute(string $routeId): void
    {
        $route = $this->findRoute($routeId);
        RouteValidationService::validateDeleteRoute($route);

        $file = $this->fileManager->getContentsString();

        // Crear patrón regex para buscar desde FullRoute::make('id') hasta ->setEndBlock('id')
        $pattern = '/
            (,)?\s*                                             # Grupo 1: coma inicial si existe
            FullRoute::make\(\s*[\'"]' . preg_quote($routeId, '/') . '[\'"]\s*\)  # FullRoute::make()
            .*?                                                # cualquier cosa entre medio (lazy)
            ->setEndBlock\(\s*[\'"]' . preg_quote($routeId, '/') . '[\'"]\s*\)    # ->setEndBlock()
            (,)?                                               # Grupo 2: coma final si existe
            (?=(\r?\n|\r))                                     # Lookahead: conserva salto de línea (no se elimina)
        /sx';


        // Aplicar la eliminación
        $newFile = preg_replace($pattern, '$1', $file, 1);


        if ($newFile === $file) {
            throw new \Exception("No se pudo encontrar el bloque para eliminar con ID: {$routeId}");
        }

        $this->fileManager->putContents($newFile);
    }

    protected function insertRouteContent(FullRoute $parentRoute, string $nuevoBloque): void
    {
        $file = $this->fileManager->getContentsString();
        $parentId = $parentRoute->getId();

        if ($parentId === null) {
            preg_match('/return\s+\[.*?\];/s', $file, $match, PREG_OFFSET_CAPTURE);
            if (!$match) {
                throw new \Exception("No se encontró el array principal.");
            }

            $arrayStart = $match[0][1];
            $arrayContent = rtrim($match[0][0], "];") . "\n" .
                self::indentBlock(trim($nuevoBloque) . ',', str_repeat(" ", 4)) . "\n];";

            $file = substr_replace($file, $arrayContent, $arrayStart, strlen($match[0][0]));
            $this->fileManager->putContents($file);
            return;
        }

        preg_match("/FullRoute::make\(['\"]{$parentId}['\"]\)/", $file, $padreMatch, PREG_OFFSET_CAPTURE);
        if (!$padreMatch) {
            throw new \Exception("No se encontró el FullRoute con ID: {$parentId}");
        }

        $padreOffset = $padreMatch[0][1];
        $setChildrenOffset = strpos($file, '->setChildrens(', $padreOffset);
        if ($setChildrenOffset === false) {
            throw new \Exception("No se encontró setChildrens para el FullRoute con ID: {$parentId}");
        }

        $openParenPos = strpos($file, '(', $setChildrenOffset);
        $currentPos = $openParenPos + 1;
        $parenCount = 1;
        
        while ($parenCount > 0 && $currentPos < strlen($file)) {
            if ($file[$currentPos] === '(') $parenCount++;
            elseif ($file[$currentPos] === ')') $parenCount--;
            $currentPos++;
        }

        $fullMethodCall = substr($file, $setChildrenOffset, $currentPos - $setChildrenOffset);
        $contentInside = trim(substr($fullMethodCall, strlen('->setChildrens('), -1));

        if (!str_starts_with(trim($contentInside), '[')) {
            $contentInside = "[\n" .
                self::indentBlock(trim($nuevoBloque) . ',', str_repeat(" ", 16)) .
                "\n" .
                self::indentBlock(trim($contentInside) . ',', str_repeat(" ", 16)) .
                "\n            ]";
        } else {
            $contentInside = trim($contentInside, "[]");
            $nuevoContenido = self::indentBlock(trim($nuevoBloque) . ',', str_repeat(" ", 16));

            if (!empty($contentInside)) {
                $nuevoContenido .= "\n" . self::indentBlock($contentInside, str_repeat(" ", 16));
            }

            $contentInside = "[\n" . $nuevoContenido . "\n            ]";
        }

        $nuevoMetodo = "->setChildrens($contentInside)";
        $file = substr_replace($file, $nuevoMetodo, $setChildrenOffset, $currentPos - $setChildrenOffset);

        $this->fileManager->putContents($file);
    }

    public function getAllFlattenedRoutes(Collection $routes): Collection
    {
        return $routes->flatMap(function (FullRoute $route) {
            return collect([$route])->merge($this->getAllFlattenedRoutes(collect($route->getChildrens())));
        });
    }

    public function exists(string $routeId): bool
    {
        return $this->findRoute($routeId) !== null;
    }

    protected static function buildFullRouteString(FullRoute $route): string
    {
        $props = $route->getProperties();
        $id = $props['id'] ?? 'undefined';
        $code = "FullRoute::make('{$id}')\n";

        foreach ($props as $prop => $value) {
            if (
                $prop === 'id' || $value === null ||
                (is_array($value) && empty($value) && $prop !== 'childrens') ||
                $prop === 'endBlock'
            ) continue;

            $method = "->set" . ucfirst($prop);

            if (is_string($value)) {
                $code .= "$method('{$value}')";
                # valida si es arreglo y ademas debe ser distinto de Childrens para no entrar en un bucle infinito
            } elseif (is_array($value)) {
                $exported = self::exportArray($value);
                $code .= "$method({$exported})";
            } elseif (is_bool($value)) {
                $code .= "$method(" . ($value ? 'true' : 'false') . ")";
            } elseif (is_numeric($value)) {
                $code .= "$method({$value})";
            }

            $code .= "\n";
        }
        // agregar al final setEndBlock('id') al final
        $code .= "->setEndBlock('{$id}')\n";

        return $code;
    }

    protected static function exportArray(array $array): string
    {
        return '[' . implode(', ', array_map(function ($v) {
            return is_string($v) ? "'$v'" : $v;
        }, $array)) . ']';
    }

    protected static function indentBlock(string $block, string $indent): string
    {
        return implode("\n", array_map(fn($line) => $indent . $line, explode("\n", $block)));
    }
}
