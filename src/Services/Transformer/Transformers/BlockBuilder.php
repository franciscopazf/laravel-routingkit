<?php

namespace Fp\FullRoute\Services\Transformer\Transformers;

use Fp\FullRoute\Clases\FullRoute;
use Fp\FullRoute\Services\Route\Strategies\RouteContentManager;
use Illuminate\Support\Str;

class BlockBuilder
{

    public function __construct(
        private RouteContentManager $manager,
        private bool $onlyStringSupport = true
    ) {}

    public static function make(RouteContentManager $manager, bool $onlyStringSupport = true): self
    {
        return new self($manager, $onlyStringSupport);
    }

    public function getBlock(FullRoute $route): string
    {
        return $this->onlyStringSupport
            ? $this->rebuildRouteContent($route)
            : $this->getBlockFromFile($route);
    }

    public function indentBlock(string $block, int $level = 2): string
    {
        $indent = $this->getSpacesByLevel($level);

        try {
            $newBlock = collect(explode("\n", $block))
                ->map(function ($line) use ($indent, $level) {
                    // Elimina espacios iniciales
                    $line = preg_replace('/^\s+/', '', $line);

                    // Si empieza con '->set', aplica doble indentación
                    if (Str::startsWith($line, '->set')) {
                        return $indent . "    " . $line;
                    }

                    // Si no, indentación normal
                    return $indent . $line;
                })
                ->join("\n");
            #echo "\nIndentando bloque :  " . $level . $newBlock;
            return $newBlock;
        } catch (\Throwable $th) {
            return $block;
        }
    }

    public function getSpacesByLevel(int $level): string
    {
        return str_repeat("    ", $level);
    }

    public function getLevelIdent(FullRoute $route): int
    {
        return match ($route->getLevel()) {
            0 => 1,
            default => (2 * $route->getLevel()) + 1
        };
    }

    public function sanitizeForArray(string $block, FullRoute $route): string
    {
        return preg_replace_callback(
            $this->getChildrenPattern($route->getId()),
            fn() => "->setChildrens([])\n->setEndBlock('{$route->getId()}')",
            $block
        );
    }

    public function insertChildren(string $block, string $children, FullRoute $route, int $level): string
    {
        $indent = str_repeat("    ", $level + 1);

        $children = trim($children)
            ? "->setChildrens([\n" . $children . "\n$indent])\n"
            : "->setChildrens([])\n";
        $end = $indent . "->setEndBlock('{$route->getId()}')";

        return preg_replace_callback(
            $this->getChildrenPattern($route->getId()),
            fn() => $children . $end,
            $block
        );
    }

    private function getBlockFromFile(FullRoute $route): string
    {
        $file = $this->manager->getContentsString();
        $pattern = $this->getBlockPattern($route->getId());

        if (!preg_match($pattern, $file, $matches))
            return $this->rebuildRouteContent($route);

        return $matches[0];
    }



    private function rebuildRouteContent(FullRoute $route, bool $setParent = false): string
    {
        $props = collect($route->getProperties());
        $id = $props->get('id', 'undefined');
        //dd($props);
        $code = "\nFullRoute::make('{$id}')\n";

        // Filtrar las propiedades que no deben procesarse
        $filtered = $props->reject(function ($value, $key) use ($setParent) {
            return $key === 'id'
                || $value === null
                || (is_array($value) && empty($value) && $key !== 'childrens')
                || in_array($key, ['endBlock', 'level', 'parent', 'childrens']);
            #  || ($key === 'parentId' && $setParent);
        });

        // Mapear cada propiedad a su llamada de método
        foreach ($filtered as $prop => $value) {
            $method = "    ->set" . ucfirst($prop);

            $code .= match (true) {
                is_string($value) => "$method('{$value}')",
                is_array($value)  => "$method({$this->exportArray($value)})",
                is_bool($value)   => "$method(" . ($value ? 'true' : 'false') . ")",
                is_numeric($value) => "$method({$value})",
                default => '', // ignorar valores no válidos
            };

            $code .= "\n";
        }

        // Agregar setEndBlock al final
        $code .= "->setChildrens([])\n";
        $code .= "->setEndBlock('{$id}')";

        return $code;
    }

    public function sanatizeContent(string $block): string
    {
        // Elimina espacios y tabs de líneas vacías, pero conserva los saltos de línea
        $block = preg_replace('/^[ \t]+(?=\r?\n)/m', '', $block);

        // Reemplaza dos o más saltos de línea seguidos por uno solo
        $block = preg_replace("/(\r?\n){2,}/", "\n\n", $block);

        return $block;
    }

    private function exportArray(array $array): string
    {
        return '[' . implode(', ', array_map(function ($v) {
            return is_string($v) ? "'$v'" : $v;
        }, $array)) . ']';
    }


    public function getHeaderBlock(): string
    {
        $file =  $this->manager->getContentsString();

        if (!preg_match($this->getHeaderPatterns(), $file, $matches))
            throw new \Exception("No se encontró el bloque de encabezado");

        return $matches[0] . "\n";
    }

    // PATERNS SECTION

    private function getHeaderPatterns(): string
    {
        return $pattern = '/<\?php.*?return\s*\[/sx';
    }

    // funcion que recive un parametro un string y
    // retorna el patron que permite buscar rutas
    // en el archivo de rutas.
    public function getChildrenPattern(string $routeId): string
    {
        return '/
        ->setChildrens\((.*?)\)                     # Grupo 1: contenido dentro del setChildrens(...)
        \s*                                         # posibles espacios o saltos de línea
        ->setEndBlock\(\s*[\'"]' . preg_quote($routeId, '/') . '[\'"]\s*\)   # ->setEndBlock("ID")
        /sx'; // ⚠️ 's' para que el punto incluya saltos de línea, 'x' para comentarios legibles
    }

    // funcion que recive un parametro un string y 
    // retorna el patron que permite buscar rutas
    // en el archivo de rutas.
    private function getBlockPattern(string $routeId): string
    {
        return $pattern = '/
            FullRoute::make\(\s*[\'"]' . preg_quote($routeId, '/') . '[\'"]\s*\)  # FullRoute::make()
            .*?                                                # cualquier cosa entre medio (lazy)
            ->setEndBlock\(\s*[\'"]' . preg_quote($routeId, '/') . '[\'"]\s*\)    # ->setEndBlock()
           /sx';
    }
}
