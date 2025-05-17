<?php

namespace Fp\FullRoute\Services;

use Fp\FullRoute\Clases\FullRoute;
use Illuminate\Support\Collection;

class RouteService
{
    protected static string $filePath = '';

    /**
     * Agrega una ruta al archivo de configuración.
     *
     * @param FullRoute $route
     * @throws \Exception
     */
    public static function addRoute(FullRoute $route): void
    {
        RouteValidationService::validateInsertRoute($route);
        $bloque = self::buildFullRouteString($route);
        $parentRoute = $route->getParentRoute();
        self::insertRouteContent($parentRoute, $bloque);
    }


    // funcion para obtener todas las rutas mapearlas y los hijos establecer el parentId
    // con el id de la ruta actual
    public static function getAllRoutes(): Collection
    {
        $routes = config('fullroute_config');

        // función recursiva para establecer parentId y referencia al objeto padre
        $setParentRefs = function ($node, $parent = null) use (&$setParentRefs) {
            if ($parent !== null) {
                $node->setParentId($parent->getId());
                $node->setParent($parent); // ← Aquí guardas el objeto padre por referencia
            }

            foreach ($node->getChildrens() as $child) {
                $setParentRefs($child, $node);
            }

            return $node;
        };

        $updated = collect($routes)->map(function ($route) use ($setParentRefs) {
            return $setParentRefs($route);
        });

        return $updated;
    }

    // implementar una funcion que busque una ruta dado un id y lo retorne
    // pasando el arreglo que esta en config como una colleccion de FullRoute
    public static function findRoute(string $routeId): ?FullRoute
    {
        //  dd( RouteValidationService::flattenRoutes(
        //      collect(config('fullroute_config'))));
        $foundRoute = self::getAllFlattenedRoutes(
            self::getAllRoutes()
        )->first(function (FullRoute $route) use ($routeId) {
            return $route->getId() === $routeId;
        });
        return $foundRoute ?: null;
    }





    public static function moveRoute(FullRoute $fromRoute, FullRoute $toRoute): void
    {
        RouteValidationService::validateMoveRoute($fromRoute);
        static::$filePath = base_path('config/fullroute_config.php');
        $file = file_get_contents(self::$filePath);

        $fromRouteId = $fromRoute->getId();

        // Extraer el bloque de la ruta
        $pattern = '/FullRoute::make\(\s*[\'"]' . preg_quote($fromRouteId, '/') . '[\'"]\)(.*?)?->setEndBlock\(\s*[\'"]' . preg_quote($fromRouteId, '/') . '[\'"]\)/s';
        if (!preg_match($pattern, $file, $matches)) {
            throw new \Exception("No se encontró la ruta con ID {$fromRouteId}");
        }

        $bloque = $matches[0];

        // Eliminar la ruta original
        self::removeRoute($fromRouteId);


        // Insertar el bloque modificado en la nueva posición
        self::insertRouteContent($toRoute, $bloque);
    }


    /**
     * Elimina una ruta del archivo de configuración.
     *
     * @param string $routeId
     * @throws \Exception
     */
    public static function removeRoute(string $routeId): void
    {   
        RouteValidationService::validateDeleteRoute($routeId);
        static::$filePath = base_path('config/fullroute_config.php');
        $file = file_get_contents(static::$filePath);

        // Crear patrón regex para buscar desde FullRoute::make('id') hasta ->setEndBlock('id')
        $pattern = '/(,?\s*)?FullRoute::make\(\s*[\'"]' . preg_quote($routeId, '/') . '[\'"]\)(.*?)?->setEndBlock\(\s*[\'"]' . preg_quote($routeId, '/') . '[\'"]\s*\)(,?\s*)/s';

        // Aplicar la eliminación
        $newFile = preg_replace($pattern, '', $file, 1);

        if ($newFile === $file) {
            throw new \Exception("No se pudo encontrar el bloque para eliminar con ID: {$routeId}");
        }

        file_put_contents(static::$filePath, $newFile);
    }


    /**
     * Inserta el bloque de ruta en el archivo de configuración.
     *
     * @param FullRoute $route
     * @param string $nuevoBloque
     * @throws \Exception
     */
    protected static function insertRouteContent(FullRoute $parentRoute, string $nuevoBloque): void
    {
        static::$filePath = base_path('config/fullroute_config.php');
        $file = file_get_contents(self::$filePath);

        $parentId = $parentRoute->getId();
        if ($parentId === null) {
            preg_match('/return\s+\[.*?\];/s', $file, $match, PREG_OFFSET_CAPTURE);
            if (!$match) {
                throw new \Exception("No se encontró el array principal en el archivo de configuración.");
            }

            $arrayStart = $match[0][1];
            $arrayContent = $match[0][0];

            $arrayContent = rtrim($arrayContent, "];") . "\n" .
                self::indentBlock(trim($nuevoBloque) . ',', str_repeat(" ", 4)) . "\n];";

            $file = substr_replace($file, $arrayContent, $arrayStart, strlen($match[0][0]));
            file_put_contents(self::$filePath, $file);
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
        $length = strlen($file);
        $parenCount = 1;

        while ($parenCount > 0 && $currentPos < $length) {
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
        file_put_contents(self::$filePath, $file);
    }


    /**
     * Aplanar la colección de rutas
     *
     * @param Collection $routes
     * @return Collection
     */
    public static function getAllFlattenedRoutes(Collection $routes): Collection
    {
        return $routes->flatMap(function (FullRoute $route) {
            $children = collect($route->getChildrens());
            return collect([$route])->merge(self::getAllFlattenedRoutes($children));
        });
    }

    /**
     * Genera una cadena de texto que representa la ruta completa.
     *
     * @param FullRoute $route
     * @return string
     */
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

    /**
     * Indenta un bloque de texto con el indentador dado.
     *
     * @param string $block
     * @param string $indent
     * @return string
     */
    protected static function indentBlock(string $block, string $indent): string
    {
        return implode("\n", array_map(fn($line) => $indent . $line, explode("\n", $block)));
    }

    /**
     * Exporta un array a una cadena de texto.
     *
     * @param array $array
     * @return string
     */
    protected static function exportArray(array $array): string
    {
        $exported = '[';
        $indexed = array_keys($array) === range(0, count($array) - 1);

        foreach ($array as $key => $value) {
            $exported .= $indexed ? '' : "'$key' => ";
            if (is_string($value)) {
                $exported .= "'$value', ";
            } elseif (is_bool($value)) {
                $exported .= $value ? 'true, ' : 'false, ';
            } elseif (is_array($value)) {
                $exported .= self::exportArray($value) . ", ";
            } else {
                $exported .= "{$value}, ";
            }
        }

        return rtrim($exported, ', ') . ']';
    }

    /**
     * Limpia los marcadores de posición en el archivo de configuración.
     *
     * @throws \Exception
     */
    public static function cleanPlaceholders(): void
    {
        static::$filePath = base_path('config/fullroute_config.php');
        $file = file_get_contents(self::$filePath);
        $file = str_replace('{{ nuevo_valor }}', '', $file);
        file_put_contents(self::$filePath, $file);
    }
}
