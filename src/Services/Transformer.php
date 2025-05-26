<?php

namespace Fp\FullRoute\Services;

use Fp\FullRoute\Clases\FullRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Fp\FullRoute\Services\RouteContentManager;
use Fp\FullRoute\Traits\AuxiliarFilesTrait;

/*
* ESTA CLASE, RECIVE COMO PARAMETRO UNA COLLECTION DE FULLROUTE
* Y "FORMATEA LA COLLECCION IDENTANDOLA CORRECTAMENTE
* ADEMAS DE "PARSEARLA" A UN ARRAY PLANO O A FORMATO DE ARBOL
*
*/
class Transformer
{
    use AuxiliarFilesTrait;

    private string $typeOfSave = "array"; // por defecto se guarda como array
    private bool $onlyStringSupport;  // por defecto se soporta solo string

    public function __construct(
        private RouteContentManager $routeContentManager,
        private Collection $fullRouteCollection,
    ) {
        $this->onlyStringSupport = config('fproute.only_string_support', true);
    }

    public static function make(
        RouteContentManager $routeContentManager,
        Collection $fullRouteCollection
    ): self {

        return new self($routeContentManager, $fullRouteCollection);
    }

    public function setonlyStringSupport(bool $onlyStringSupport): self
    {
        $this->onlyStringSupport = $onlyStringSupport;
        return $this;
    }

    public function getonlyStringSupport(): bool
    {
        return $this->onlyStringSupport;
    }

    public function setTypeOfSave(string $type): self
    {
        $this->typeOfSave = $type;
        return $this;
    }

    public function getTypeOfSave(): string
    {
        return $this->typeOfSave;
    }

    public function getFunctionOfSave(): string
    {
        return match ($this->typeOfSave) {
            'array' => 'prepareContentForArray',
            'tree' => 'prepareContentForTree',
            default => throw new \InvalidArgumentException("Tipo de guardado no válido: {$this->typeOfSave}"),
        };
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
        //dd($content);

        $content = $header . $content . $footer;

        $content = $this->sanitizeBlock($content);

        return $content;
    }


    private function getHeaderBlock(): string
    {
        $file = $this->routeContentManager->getContentsString();
        if (!preg_match($this->getHeaderPatterns(), $file, $matches)) {

            throw new \Exception("No se encontró el bloque de encabezado");
        }
        return $matches[0] . "\n";
    }

    private function getContentBlock(): string
    {
        $functionOfSave = $this->getFunctionOfSave();
        $content = "";

        $count = count($this->fullRouteCollection);
        $index = 0;

        foreach ($this->fullRouteCollection as $route) {
            $index++;
            $content .= $this->$functionOfSave($route);

            if (($index < $count) && $this->typeOfSave === 'tree') {
                $content .= ",\n";
            }
        }

        return $content;
    }

    private function getFooterBlock(): string
    {
        return "\n];\n";
    }


    // recorre un arbol y en lugar de concatenar las rutas dentro de las rutas
    // padres las concatena en un array plano ese es el nuevo contenido :)
    private function prepareContentForArray(FullRoute $route): string
    {
        // Obtener el bloque actual y limpiar hijos
        $block = $this->getBlock($route);
        //echo "\nBloque encontrado: " . $block;

        $block = preg_replace_callback(
            $this->getChildrenPattern($route->getId()),
            function () use ($route) {
                return "->setChildrens([])"
                    . "\n        ->setEndBlock('{$route->getId()}')";
            },
            $block
        );

        $content = $this->indentBlock($block, 1);

        $content = $content . ",\n";
        // Acumular contenido de hijos recursivamente
        foreach ($route->getChildrens() as $child) {
            $content .=  $this->prepareContentForArray($child);
        }

        return $content;
    }

    private function getLevelIdent(FullRoute $route): int
    {
        $pluss = match ($route->getLevel()) {
            0 => 1,
            // 2(X) 1
            default => (2 * $route->getLevel()) + 1
        };

        $levelIdent = $pluss;
        return $levelIdent;
    }

    private function prepareContentForTree(FullRoute $route): string
    {

        $levelIdent = $this->getLevelIdent($route);
        // 1. Obtener bloque sin indentar del padre
        $block = $this->getBlock($route);
        // identa el bloque actual
        $block = $this->indentBlock($block, $levelIdent);

        // 2. Preparar hijos sin indentarlos aún
        $childBlocks = [];

        foreach ($route->getChildrens() as $child) {
            // El hijo también sigue este flujo, y no se indenta aquí
            $childBlocks[] = $this->prepareContentForTree($child);
        }

        // 3. Unir hijos sin indentación (por ahora)
        $joinedChildren = implode(",\n", $childBlocks);
        $indent = $this->getSpacesByLevel($levelIdent + 1);
        // 4. Insertar hijos con indentación calculada al momento
        $block = preg_replace_callback(
            $this->getChildrenPattern($route->id),
            function () use ($joinedChildren, $route, $indent) {

                $indent = $this->getSpacesByLevel($this->getLevelIdent($route));
                // Si no hay hijos, se establece un array vacío
                if (trim($joinedChildren) === '') {
                    return  "->setChildrens([])\n"
                        . $indent . "    ->setEndBlock('{$route->id}')";
                }

                // Cálculo de indentación en función del nivel actual
                $indentedChildren = $joinedChildren;

                return "->setChildrens([\n" .
                    $indentedChildren .
                    "\n$indent    ])\n"
                    .  $indent . "    ->setEndBlock('{$route->id}')";
            },
            $block
        );

        // 5. Finalmente, indentar el bloque completo del padre si lo vas a insertar en otro nivel
        // Pero solo si este bloque no es el raíz
        return $block;
    }



    private function getBlockForArrayFunction(): string
    {
        return "";
        // EN CASO DE QUE SEA UN CAMBIO DESDE UN ARBOL HAY QUE AGREGAR EL SETPARENTID
        // QUITAR EL ->setChildrens([]) PORQUE EN FORMA DE ARREGLO NO SE NECESITA
    }

    private function quitSetParentId(string $block): string
    {
        // Elimina el setParentId del bloque
        $block = preg_replace('/->setParentId\([\'"]?([^\'"]+)[\'"]?\)/', '', $block);
        return $block;
    }

    private function getBlockForTreeFunction(): string
    {
        return "";
        // se prepara el bloque para funcionar como arbol
        // es decir se sanatiza para que no tenga el atributo setParentId
        // porque no es necesario en el arbol
        // (ADEMAS CONSIDERAR SI ES POSIBLE EN CASO DE QUE NO TENGA HIJOS) QUITAR EL ->setChildrens([])
    }


    private function indentBlock(string $block, int $level = 2): string
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

    private function getSpacesByLevel(int $level): string
    {
        return str_repeat("    ", $level);
    }

    private function getBlock(FullRoute $route): string
    {
        // funciona como punto de redireccion para obtener el bloque de un 
        // archivo o ir a formarlo de nuevo
        if ($this->onlyStringSupport)
            return $this->rebuildRouteContent($route);
        else
            return $this->getBlockFromFile($route);
    }


    // BLOCKS SECTIONS

    private function getBlockFromFile(FullRoute $route): string
    {
        $file = $this->routeContentManager->getContentsString();
        $fromRouteId = $route->getId();

        $pattern = $this->getBlockPattern($fromRouteId);

        if (!preg_match($pattern, $file, $matches)) {
            // si no se encuentra el bloque entonces se llama a reconstruir
            // porque es nuevo :)
            return $this->rebuildRouteContent($route);
            throw new \Exception("No se encontró la ruta con ID {$fromRouteId}");
        }
        $newBlock = "\n    " . $matches[0];
        # echo "\nBloque Encontrado: " . $newBlock;
        return $newBlock;
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

    private function sanitizeBlock(string $block): string
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

    // PATERNS SECTION

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
        /sx'; // ⚠️ 's' para que el punto incluya saltos de línea, 'x' para comentarios legibles
    }

    // funcion que recive un parametro un string y retorna el patron que permite buscar rutas
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
