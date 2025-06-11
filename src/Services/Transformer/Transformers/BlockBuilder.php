<?php

namespace Fp\FullRoute\Services\Transformer\Transformers;

use Fp\FullRoute\Contracts\FpEntityInterface;
use Fp\FullRoute\Services\Route\Strategies\RouteContentManager;

use Illuminate\Support\Collection;


use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;


class BlockBuilder
{

    public function __construct(
        private RouteContentManager $manager,
        private bool $onlyStringSupport = true,
        private string $className = 'FpEntityInterface'
    ) {}

    public static function make(RouteContentManager $manager, bool $onlyStringSupport = true): self
    {
        return new self($manager, $onlyStringSupport);
    }

    public function getBlock(FpEntityInterface $entity): string
    {
        $block =  $this->manager->onlyStringSupport
            ? $this->rebuildRouteContent($entity)
            : $this->getBlockFromFile($entity);

        return "\n" . $block;
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

    public function getLevelIdent(FpEntityInterface $entity): int
    {
        return match ($entity->getLevel()) {
            0 => 1,
            default => (2 * $entity->getLevel()) + 1
        };
    }

    public function sanitizeForArray(string $block, FpEntityInterface $entity): string
    {
        return preg_replace_callback(
            $this->getChildrenPattern($entity->getId()),
            fn() => "->setChildrens([])\n->setEndBlock('{$entity->getId()}')",
            $block
        );
    }

    public function insertChildren(string $block, string $children, FpEntityInterface $entity, int $level): string
    {
        $indent = str_repeat("    ", $level + 1);

        $children = trim($children)
            ? "->setChildrens([\n" . $children . "\n$indent])\n"
            : "->setChildrens([])\n";
        $end = $indent . "->setEndBlock('{$entity->getId()}')";

        return preg_replace_callback(
            $this->getChildrenPattern($entity->getId()),
            fn() => $children . $end,
            $block
        );
    }

    private function getBlockFromFile(FpEntityInterface $entity): string
    {
        $file = $this->manager->getContentsString();
        $pattern = $this->getBlockPattern($entity);

        if (!preg_match($pattern, $file, $matches))
            return $this->rebuildRouteContent($entity);

        return $matches[0];
    }




    private function rebuildRouteContent(FpEntityInterface $entity, bool $setParent = false): string
    {
        $props = collect($entity->getProperties());
        $classPattern =  (new ReflectionClass($entity))->getShortName();

        $code = $this->getMakerPattern($entity) . "\n";

        $validator = AttributeOmitter::make(object: $entity);
        $filtered = $props->reject(function ($value, $key) use ($validator) {
            // Puedes añadir lógica adicional aquí para omitir las propiedades que ya se usaron
            // en el método maker si lo deseas.
            return $validator->setAttribute($key)->validate();
        });

        foreach ($filtered as $prop => $value) {
            $method = "    ->set" . ucfirst($prop);

            $code .= match (true) {
                is_string($value) => "$method('{$value}')",
                is_array($value)  => "$method({$this->exportArray($value)})",
                is_bool($value)   => "$method(" . ($value ? 'true' : 'false') . ")",
                is_numeric($value) => "$method({$value})",
                default => '',
            };
            $code .= "\n";
        }

        $code .= "->setChildrens([])\n";
        $code .= "->setEndBlock('{$props->get('id', 'undefined')}')";

        return $code;
    }

    /**
     * Genera dinámicamente la llamada al método 'make' con sus parámetros,
     * enfocándose en agregar un máximo de dos parámetros distintos.
     *
     * @param FpEntityInterface $entity La entidad de la cual se obtendrán las propiedades y la clase.
     * @return string La cadena de código para el método 'make'.
     */
    public function getMakerPattern(FpEntityInterface $entity): string
    {
        $reflectionClass = new ReflectionClass($entity);
        $className = $reflectionClass->getShortName();
        $makerMethodName = $entity->getProperties()['makerMethod'] ?? 'make';

        if (!$reflectionClass->hasMethod($makerMethodName)) {
            $id = $entity->getProperties()['id'] ?? 'undefined';
            return "$className::$makerMethodName('{$id}')";
        }

        $reflectionMethod = $reflectionClass->getMethod($makerMethodName);
        $parameters = $reflectionMethod->getParameters();

        $props = collect($entity->getProperties());
        $args = [];
        $addedParameters = []; // Para llevar un registro de los parámetros ya agregados

        foreach ($parameters as $parameter) {
            $paramName = $parameter->getName();
            $paramValue = $props->get($paramName);

            // Formatear el valor
            $formattedValue = match (true) {
                is_string($paramValue) => "'{$paramValue}'",
                is_array($paramValue)  => $this->exportArray($paramValue),
                is_bool($paramValue)   => $paramValue ? 'true' : 'false',
                is_numeric($paramValue) => (string) $paramValue,
                $paramValue === null && $parameter->isOptional() && $parameter->isDefaultValueAvailable() => (string) $parameter->getDefaultValue(),
                $paramValue === null => 'null', // Manejar explícitamente los nulls si no tienen valor por defecto
                default => '',
            };

            // Lógica para agregar solo dos parámetros distintos
            if (count($addedParameters) < 2) {
                if (!in_array($formattedValue, $addedParameters) || $formattedValue === 'null') { // Permitir agregar 'null' siempre, o podrías ajustarlo
                    $args[] = $formattedValue;
                    $addedParameters[] = $formattedValue;
                }
            } else {
                // Si ya tenemos dos parámetros distintos, salimos del bucle.
                // Si el método 'make' tiene más de 2 parámetros, los demás se ignorarán.
                break;
            }
        }

        return "$className::$makerMethodName(" . implode(', ', $args) . ")";
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
        $isAssoc = array_keys($array) !== range(0, count($array) - 1);

        $items = array_map(function ($key, $value) {
            // Si el valor es un arreglo, aplicar recursivamente
            $exportedValue = is_array($value) ? $this->exportArray($value) : (is_string($value) ? "'$value'" : $value);

            // Si la clave es numérica, no incluirla (arreglo indexado)
            if (is_int($key)) {
                return $exportedValue;
            }

            // Si es clave no numérica, incluir la clave => valor
            return "'$key' => $exportedValue";
        }, array_keys($array), $array);

        return '[' . implode(', ', $items) . ']';
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
    public function getChildrenPattern(string $entityId): string
    {
        return '/
        ->setChildrens\((.*?)\)                     # Grupo 1: contenido dentro del setChildrens(...)
        \s*                                         # posibles espacios o saltos de línea
        ->setEndBlock\(\s*[\'"]' . preg_quote($entityId, '/') . '[\'"]\s*\)   # ->setEndBlock("ID")
        /sx'; // ⚠️ 's' para que el punto incluya saltos de línea, 'x' para comentarios legibles
    }

    // funcion que recive un parametro un string y 
    // retorna el patron que permite buscar rutas
    // en el archivo de rutas.
    private function getBlockPattern(FpEntityInterface $entity): string
    {

        $entityId = $entity->getId();
        $shortClass = (new \ReflectionClass($entity))->getShortName(); // Solo "FpRoute"
        $makerMethod = $entity->getMakerMethod() ?? 'make';

        return '/
        ' . preg_quote($shortClass, '/') . '::' . $makerMethod . '\(\s*[\'"]' . preg_quote($entityId, '/') . '[\'"]\s*\)  # Class::make()
        .*?
        ->setEndBlock\(\s*[\'"]' . preg_quote($entityId, '/') . '[\'"]\s*\)
    /sx';
    }
}
