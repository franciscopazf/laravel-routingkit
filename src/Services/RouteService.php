<?php

namespace Fp\FullRoute\Services;

use Fp\FullRoute\Clases\FullRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionProperty;

class RouteService
{
    private static Collection $routes;
    private FullRoute $newRoute;
    protected static string $filePath = '';

    public static function addRoute(
        FullRoute $route,
    ): void {
        $parentId = $route->getParentId();
        if ($parentId === null) {
            throw new \Exception("El ID del padre no puede ser nulo.");
        }
        static::$filePath = base_path('config/fullroute_config.php');
        $file = file_get_contents(self::$filePath);

        // Generar el nuevo bloque
        $nuevoBloque = self::buildFullRouteString($route);

        // Buscar el FullRoute del padre
        preg_match("/FullRoute::make\(['\"]{$parentId}['\"]\)/", $file, $padreMatch, PREG_OFFSET_CAPTURE);
        if (!$padreMatch) {
            throw new \Exception("No se encontró el FullRoute con ID: {$parentId}");
        }

        $padreOffset = $padreMatch[0][1];

        // Buscar el ->setChildrens( después del padre
        $setChildrenOffset = strpos($file, '->setChildrens(', $padreOffset);
        if ($setChildrenOffset === false) {
            throw new \Exception("No se encontró setChildrens para el FullRoute con ID: {$parentId}");
        }

        // Detectar el cierre correcto del paréntesis
        $openParenPos = strpos($file, '(', $setChildrenOffset);
        $currentPos = $openParenPos + 1;
        $length = strlen($file);
        $parenCount = 1;

        while ($parenCount > 0 && $currentPos < $length) {
            if ($file[$currentPos] === '(') {
                $parenCount++;
            } elseif ($file[$currentPos] === ')') {
                $parenCount--;
            }
            $currentPos++;
        }

        $fullMethodCall = substr($file, $setChildrenOffset, $currentPos - $setChildrenOffset);

        // Extraer contenido dentro de los paréntesis
        $contentInside = trim(substr($fullMethodCall, strlen('->setChildrens('), -1));

        // Convertir a array si no lo es
        if (!str_starts_with(trim($contentInside), '[')) {
            $contentInside = "[\n" . self::indentBlock(trim($contentInside) . ',', str_repeat(" ", 16)) . "\n            ]";
        } else {
            // Eliminar el cierre del array para insertar antes
            $contentInside = rtrim(rtrim($contentInside), ']');
            
            $contentInside .= "\n" . self::indentBlock(trim(self::buildFullRouteString($route)), str_repeat(" ", 16)) . "\n            ]";
            $newMethod = "->setChildrens($contentInside)";
            $file = substr_replace($file, $newMethod, $setChildrenOffset, $currentPos - $setChildrenOffset);
            file_put_contents(self::$filePath, $file);
            return;
        }

        // Insertar nuevo bloque
        $nuevoContenido = rtrim(rtrim($contentInside), ']');
        $nuevoContenido = rtrim($nuevoContenido, ',') . ',';
        $nuevoContenido .= "\n" . self::indentBlock(trim($nuevoBloque), str_repeat(" ", 16)) . "\n            ]";

        $nuevoMetodo = "->setChildrens($nuevoContenido)";
        $file = substr_replace($file, $nuevoMetodo, $setChildrenOffset, $currentPos - $setChildrenOffset);

        file_put_contents(self::$filePath, $file);
    }

    protected static function buildFullRouteString(FullRoute $route): string
    {
        $props = $route->getProperties();
        $id = $props['id'] ?? 'undefined';
        $code = "FullRoute::make('{$id}')";

        foreach ($props as $prop => $value) {
            if (
                $prop === 'id' || $value === null ||
                (is_array($value) && empty($value))
            ) continue;

            $method = "->set" . ucfirst($prop);

            if (is_string($value)) {
                $code .= "$method('{$value}')";
            } elseif (is_array($value)) {
                $exported = self::exportArray($value);
                $code .= "$method({$exported})";
            } elseif (is_bool($value)) {
                $code .= "$method(" . ($value ? 'true' : 'false') . ")";
            } elseif (is_numeric($value)) {
                $code .= "$method({$value})";
            }
        }

        return $code . ",\n";
    }

    protected static function indentBlock(string $block, string $indent): string
    {
        return implode("\n", array_map(fn($line) => $indent . $line, explode("\n", $block)));
    }

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

    public static function cleanPlaceholders(): void
    {
        static::$filePath = base_path('config/fullroute_config.php');
        $file = file_get_contents(self::$filePath);
        $file = str_replace('{{ nuevo_valor }}', '', $file);
        file_put_contents(self::$filePath, $file);
    }
}
