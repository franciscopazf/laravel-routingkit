<?php

namespace Fp\FullRoute\Traits;

use Fp\FullRoute\Clases\FullRoute;

trait AuxiliarFilesTrait
{
    protected function insertRouteContent(?FullRoute $parentRoute = null, string $nuevoBloque, int $level = 0): void
    {
        // dd($nuevoBloque);
        $level = $parentRoute?->level ?? $level;
        $file = $this->fileManager->getContentsString();
        $parentId = $parentRoute?->getId();
        $levelIndent = str_repeat(" ", (($level) * 8) + 4);
        //  dd($nuevoBloque);
        $nuevoBloque = self::indentMethods($nuevoBloque, $levelIndent);
        //  dd($nuevoBloque);
        if ($parentId === null) {
            // Buscar la última ocurrencia de '];'
            $lastArrayEnd = strrpos($file, '];');
            if ($lastArrayEnd === false) throw new \Exception("No se encontró el final del array principal.");

            // Buscar hacia atrás desde esa posición para encontrar el 'return'
            $returnPos = strrpos(substr($file, 0, $lastArrayEnd), 'return');
            if ($returnPos === false) throw new \Exception("No se encontró el 'return' que inicia el array principal.");

            // Extraer el contenido actual del array
            $arrayContent = substr($file, $returnPos, $lastArrayEnd + 2 - $returnPos); // incluye '];'

            // Quitar el cierre para insertar el nuevo bloque
            $arrayContent = rtrim($arrayContent, "];") . $nuevoBloque . "];";

            // Reemplazar en el archivo original
            $file = substr_replace($file, $arrayContent, $returnPos, $lastArrayEnd + 2 - $returnPos);

           // dd($file);
            $this->fileManager->putContents($file);
            return;
        }


        preg_match("/FullRoute::make\(['\"]{$parentId}['\"]\)/", $file, $padreMatch, PREG_OFFSET_CAPTURE);
        if (!$padreMatch) throw new \Exception("No se encontró el FullRoute con ID: {$parentId}");

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

        if ($contentInside === '[]') {
            // Solo insertar el nuevo bloque con indentación
            $nuevoMetodo = "->setChildrens([\n{$levelIndent}{$nuevoBloque}\n{$levelIndent}])";
        } else {
            // Mantener el contenido original tal como está, solo anteponer el nuevo bloque indentado
            $contentOriginal = trim($contentInside, "[] \n\t");
            $nuevoContenido = "{$levelIndent}{$nuevoBloque}";
            //  dd($nuevoBloque);

            if (!empty($contentOriginal)) {
                $nuevoContenido .= "\n" . $levelIndent . $contentOriginal;
            }

            $nuevoMetodo = "->setChildrens([\n{$nuevoContenido}\n{$levelIndent}])";
        }
        // echo $nuevoContenido;

        $file = substr_replace($file, $nuevoMetodo, $setChildrenOffset, $currentPos - $setChildrenOffset);
        $this->fileManager->putContents($file);
    }


    protected static function indentMethods(string $code, string $indent): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $code);
        return implode("\n", array_map(function ($line) use ($indent) {
            // si el metodo contiene -> al inicio entonces aumentar la identacion en 4
            if (preg_match('/^\s*->/', $line)) {
                $indent .= str_repeat(" ", 4);
            } else {
                $indent .= '';
            }
            return $indent . ltrim($line);
        }, $lines));
    }

    protected static function indentBlock(string $block, string $indent): string
    {
        return implode("\n", array_map(fn($line) => $indent . $line, explode("\n", $block)));
    }

    /**
     * Obtiene todas las rutas aplanadas.
     *
     * @param Collection $routes Colección de rutas.
     * @return Collection Colección de rutas aplanadas.
     */
    private static function buildFullRouteString(FullRoute $route, bool $setParent = false): string
    {
        $props = $route->getProperties();
        $id = $props['id'] ?? 'undefined';
        $code = "\n FullRoute::make('{$id}')\n";

        $lastKey = array_key_last($props);

        foreach ($props as $prop => $value) {
            if (
                $prop === 'id' || $value === null ||
                (is_array($value) && empty($value) && $prop !== 'childrens') ||
                $prop === 'endBlock' ||
                $prop === 'level' ||
                $prop === 'parent'
                //($prop === 'parentId' && $setParent)
            ) continue;

            $method = "    ->set" . ucfirst($prop);

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

            if ($prop !== $lastKey) {
                $code .= "\n";
            }
            //  echo $code . "=>" . $prop;
        }
        // agregar al final setEndBlock('id') al final
        $code .= "->setEndBlock('{$id}'),\n";

        return $code;
    }

    protected static function exportArray(array $array): string
    {
        return '[' . implode(', ', array_map(function ($v) {
            return is_string($v) ? "'$v'" : $v;
        }, $array)) . ']';
    }
}
