<?php

namespace Fp\FullRoute\Services;

use Fp\FullRoute\Clases\FullRoute;
use Fp\FullRoute\Services\RouteValidationService;
use Fp\FullRoute\Services\RouteContentManager;

use Illuminate\Support\Collection;

use Fp\FullRoute\Contracts\RouteStrategyInterface;

class RouteStrategyFile implements RouteStrategyInterface
{
    protected RouteContentManager $fileManager;


    /**
     * Constructor de la clase RouteStrategyFile.
     *
     * @param RouteContentManager $fileManager El gestor de contenido de rutas.
     */
    public function __construct(RouteContentManager $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    /**
     * Crea una nueva instancia de RouteStrategyFile.
     *
     * @param RouteContentManager $fileManager El gestor de contenido de rutas.
     * @return self La nueva instancia de RouteStrategyFile.
     */
    public static function make(RouteContentManager $fileManager): self
    {
        return new self($fileManager);
    }

    /**
     * Agrega una ruta al archivo de rutas.
     *
     * @param FullRoute $route La ruta a agregar.
     * @throws \Exception Si la ruta no es válida o si ocurre un error al insertar la ruta.
     */
    public function addRoute(FullRoute $route, string|FullRoute $parent): void
    {
        // Si el padre es un string, buscar la ruta correspondiente
        if (is_string($parent)) {
            $parent = $this->findRoute($parent);
        }

        RouteValidationService::make()
            ->validateRoute($route, $this->getAllRoutes());

        $bloque = self::buildFullRouteString($route);

        $this->insertRouteContent($parent, $bloque);
    }

    /**
     * Obtiene todas las rutas del archivo de rutas.
     *
     * @return Collection Colección de rutas.
     */
    public function getAllRoutes(): Collection
    {
        $routes = $this->fileManager->getContents();
        // dd($routes);
        $setParentRefs = function ($node, $parent = null, $prefixName = '', $prefixUrl = '', $level = 0) use (&$setParentRefs) {
            if ($parent !== null) {
                $node->setParentId($parent->getId());
                $node->setParent($parent);
            }

            // Concatenar jerárquicamente
            $currentName = trim($prefixName . '.' . $node->getUrlName(), '.');
            $currentUrl  = rtrim($prefixUrl . '/' . ltrim($node->getUrl(), '/'), '/');

            // Asignar valores completos
            $node->fullUrlName = $currentName;
            $node->fullUrl     = '/' . ltrim($currentUrl, '/');
            $node->setLevel($level);

            foreach ($node->getChildrens() as $child) {
                $setParentRefs($child, $node, $currentName, $currentUrl, $level + 1);
            }

            return $node;
        };

        return collect($routes)->map(fn($route) => $setParentRefs($route));
    }



    /**
     * Busca una ruta por su ID.
     *
     * @param string $routeId El ID de la ruta a buscar.
     * @return FullRoute|null La ruta encontrada o null si no se encuentra.
     */
    public function findRoute(string $routeId): ?FullRoute
    {
        return $this->getAllFlattenedRoutes($this->getAllRoutes())
            ->first(fn(FullRoute $route) => $route->getId() === $routeId);
    }


    /**
     * Busca una ruta por un nombre de parametro y su valor y retorna una colección de coincidencias.     
     * @param string $routeName el nombre del parametro a buscar.
     * @param string $value el valor del parametro a buscar.
     * @return Collection La ruta encontrada o null si no se encuentra.
     */

    public function findByParamName(string $paramName, string $value): ?Collection
    {
        return $this->getAllFlattenedRoutes($this->getAllRoutes())
            ->filter(fn(FullRoute $route) => $route->getParam($paramName) === $value);
    }

    /**
     * Busca una ruta por su nombre.
     *
     * @param string $routeName El nombre de la ruta a buscar.
     * @return FullRoute|null La ruta encontrada o null si no se encuentra.
     */
    public function findByRouteName(string $routeName): ?FullRoute
    {
        return $this->getAllFlattenedRoutes($this->getAllRoutes())
            ->first(fn(FullRoute $route) => $route->getFullUrlName() === $routeName);
    }


    /**
     * Obtiene todas las rutas aplanadas. (OPTIMIZAR O MODIFICAR LA LOGICA DE BUSQUEDA ACTUALMENTE ES DEMASIADO COStoso)
     *
     * @param Collection $routes Colección de rutas.
     * @return Collection Colección de rutas aplanadas.
     */
    public function getAllFlattenedRoutes(Collection $routes): Collection
    {
        return $routes->flatMap(function (FullRoute $route) {
            return collect([$route])->merge($this->getAllFlattenedRoutes(collect($route->getChildrens())));
        });
    }

    /**
     * Verifica si una ruta existe por su ID.
     *
     * @param string $routeId El ID de la ruta a verificar.
     * @return bool true si la ruta existe, false en caso contrario.
     */
    public function exists(string $routeId): bool
    {
        return $this->findRoute($routeId) !== null;
    }



    /**
     * Mueve una ruta de un lugar a otro.
     *
     * @param FullRoute $fromRoute La ruta de origen.
     * @param FullRoute $toRoute La ruta de destino.
     * @throws \Exception Si la ruta no es válida o si ocurre un error al mover la ruta.
     */
    public function moveRoute(FullRoute $fromRoute, FullRoute $toRoute): void
    {
        RouteValidationService::make()
            ->validateMoveRoute($fromRoute, $this->getAllRoutes());

        $file = $this->fileManager->getContentsString();
        $fromRouteId = $fromRoute->getId();

        $pattern = $this->getPattern($fromRouteId);

        if (!preg_match($pattern, $file, $matches)) {
            throw new \Exception("No se encontró la ruta con ID {$fromRouteId}");
        }

        $bloque = $matches[0];
        // eliminar los espacios del inicio del bloque
        // asignar el espacion al final del bloqu
        $bloque = preg_replace('/^\s+/m', '', $bloque);
        // quitar si existe una coma al inicio
        $bloque = preg_replace('/^,/', '', $bloque);
        $bloque = "\n". $bloque . "\n";   

        $this->removeRoute($fromRouteId);

        $this->insertRouteContent($toRoute, $bloque);
    }

    private function getPattern(string $routeId): string
    {
        return $pattern = '/
            (,)?\s*                                             # Grupo 1: coma inicial si existe
            FullRoute::make\(\s*[\'"]' . preg_quote($routeId, '/') . '[\'"]\s*\)  # FullRoute::make()
            .*?                                                # cualquier cosa entre medio (lazy)
            ->setEndBlock\(\s*[\'"]' . preg_quote($routeId, '/') . '[\'"]\s*\)    # ->setEndBlock()
            (,)?                                               # Grupo 2: coma final si existe
            (?=(\r?\n|\r))                                     # Lookahead: conserva salto de línea (no se elimina)
        /sx';
    }

    /**
     * Elimina una ruta por su ID.
     *
     * @param string $routeId El ID de la ruta a eliminar.
     * @throws \Exception Si la ruta no es válida o si ocurre un error al eliminar la ruta.
     */
    public function removeRoute(string $routeId): void
    {
        $route = $this->findRoute($routeId);

        RouteValidationService::make()
            ->validateDeleteRoute($route);

        $file = $this->fileManager->getContentsString();
        $pattern = $this->getPattern($routeId);

        // Aplicar la eliminación
        $newFile = preg_replace($pattern, '$1', $file, 1);


        if ($newFile === $file) {
            throw new \Exception("No se pudo encontrar el bloque para eliminar con ID: {$routeId}");
        }

        $this->fileManager->putContents($newFile);
    }

    protected function insertRouteContent(FullRoute $parentRoute, string $nuevoBloque): void
    {
        // dd($nuevoBloque);
        $file = $this->fileManager->getContentsString();
        $parentId = $parentRoute->getId();
        $levelIndent = str_repeat(" ", (($parentRoute->level + 1) * 8) + 4);
      //  dd($nuevoBloque);
        $nuevoBloque = self::indentMethods($nuevoBloque, $levelIndent);
      //  dd($nuevoBloque);
        if ($parentId === null) {
            preg_match('/return\s+\[.*?\];/s', $file, $match, PREG_OFFSET_CAPTURE);
            if (!$match) throw new \Exception("No se encontró el array principal.");

            $arrayStart = $match[0][1];
            $arrayContent = rtrim($match[0][0], "];") . "\n" .
                $nuevoBloque . ",\n];";

            $file = substr_replace($file, $arrayContent, $arrayStart, strlen($match[0][0]));
            $this->fileManager->putContents($file);
            return;
        }

        preg_match("/FullRoute::make\(['\"]{$parentId}['\"]\)/", $file, $padreMatch, PREG_OFFSET_CAPTURE);
        if (!$padreMatch) throw new \Exception("No se encontró el FullRoute con ID: {$parentId}");

        $padreOffset = $padreMatch[0][1];
        $setChildrenOffset = strpos($file, '->setChildrens(', $padreOffset);
        if ($setChildrenOffset === false) 
        {
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
                $nuevoContenido .= "\n" .$levelIndent.$contentOriginal;
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
    private static function buildFullRouteString(FullRoute $route): string
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
            // echo $code . "=>" . $prop;
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
