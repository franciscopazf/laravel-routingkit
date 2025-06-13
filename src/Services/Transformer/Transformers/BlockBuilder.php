<?php

namespace Fp\RoutingKit\Services\Transformer\Transformers;

use Fp\RoutingKit\Contracts\FpEntityInterface;
use Fp\RoutingKit\Services\Route\Strategies\RouteContentManager;

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
            fn() => "->setItems([])\n->setEndBlock('{$entity->getId()}')",
            $block
        );
    }

    public function insertChildren(string $block, string $Item, FpEntityInterface $entity, int $level): string
    {
        $indent = str_repeat("    ", $level + 1);

        $Item = trim($Item)
            ? "->setItems([\n" . $Item . "\n$indent])\n"
            : "->setItems([])\n";
        $end = $indent . "->setEndBlock('{$entity->getId()}')";

        return preg_replace_callback(
            $this->getChildrenPattern($entity->getId()),
            fn() => $Item . $end,
            $block
        );
    }

    private function getBlockFromFile(FpEntityInterface $entity): string
    {
        $file = $this->manager->getContentsString();
        // Usamos getBlockPattern para la búsqueda
        $pattern = $this->getBlockPattern($entity);

        if (!preg_match($pattern, $file, $matches)) {
            echo "\n " . $pattern;
            echo "\nNo se encontró el bloque para la entidad: " . $entity->getId();
            return $this->rebuildRouteContent($entity);
        }

        return $matches[0];
    }

    private function rebuildRouteContent(FpEntityInterface $entity, bool $setParent = false): string
    {
        $props = collect($entity->getProperties());
        // Usamos getMakerCallLiteral para la creación de la cadena literal
        $code = $this->getMakerCallLiteral($entity) . "\n";

        // Asegúrate de que AttributeOmitter esté definido o incluido si es una clase externa.
        // Si no está definido, esto causará un error fatal.
        // Por ahora, asumiré que AttributeOmitter existe y es accesible.
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

        $code .= "->setItems([])\n";
        $code .= "->setEndBlock('{$props->get('id', 'undefined')}')";

        return $code;
    }

    /**
     * Genera dinámicamente la llamada al método 'make' con sus parámetros,
     * para ser insertada como código literal en un archivo.
     * Enfocándose en agregar un máximo de dos parámetros.
     *
     * @param FpEntityInterface $entity La entidad de la cual se obtendrán las propiedades y la clase.
     * @return string La cadena de código literal para el método 'make'.
     */
    public function getMakerCallLiteral(FpEntityInterface $entity): string
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
        $addedParameters = [];

        foreach ($parameters as $parameter) {
            $paramName = $parameter->getName();
            $paramValue = $props->get($paramName);

            $formattedValue = match (true) {
                is_string($paramValue) => "'{$paramValue}'",
                is_array($paramValue)  => $this->exportArray($paramValue),
                is_bool($paramValue)   => $paramValue ? 'true' : 'false',
                is_numeric($paramValue) => (string) $paramValue,
                $paramValue === null && $parameter->isOptional() && $parameter->isDefaultValueAvailable() => (string) $parameter->getDefaultValue(),
                $paramValue === null => 'null',
                default => '',
            };

            if (count($addedParameters) < 2) {
                if (!in_array($formattedValue, $addedParameters) || $formattedValue === 'null') {
                    $args[] = $formattedValue;
                    $addedParameters[] = $formattedValue;
                }
            } else {
                break;
            }
        }

        // Formato limpio con un espacio después de la coma
        return "$className::$makerMethodName(" . implode(', ', $args) . ")";
    }


    /**
     * Genera dinámicamente el patrón de expresión regular para la llamada al método 'make'.
     *
     * - Si el método tiene 0 o 1 parámetro, genera el patrón correspondiente.
     * - Si tiene 2 o más parámetros Y TODOS son iguales, genera un patrón como si solo hubiera uno.
     * - Si tiene 2 o más parámetros Y SON DISTINTOS, genera un patrón con los primeros dos parámetros.
     *
     * @param FpEntityInterface $entity La entidad de la cual se obtendrán las propiedades y la clase.
     * @return string El patrón regex para la llamada al método 'make'.
     */
    public function getMakerPattern(FpEntityInterface $entity): string
    {
        $reflectionClass = new ReflectionClass($entity);
        $className = $reflectionClass->getShortName();
        $makerMethodName = $entity->getProperties()['makerMethod'] ?? 'make';

        // Escapamos el nombre de la clase y el método para regex
        $escapedClassName = preg_quote($className, '/');
        $escapedMakerMethodName = preg_quote($makerMethodName, '/');

        // Caso especial si el método 'make' no existe en la clase
        if (!$reflectionClass->hasMethod($makerMethodName)) {
            $id = $entity->getProperties()['id'] ?? 'undefined';
            $escapedId = preg_quote($id, '/');
            return "$escapedClassName\\s*::\\s*$escapedMakerMethodName\\s*\\(\\s*'{$escapedId}'\\s*\\)";
        }

        $reflectionMethod = $reflectionClass->getMethod($makerMethodName);
        $parameters = $reflectionMethod->getParameters();
        $props = collect($entity->getProperties());

        // 1. Recolectamos los patrones de TODOS los argumentos disponibles
        $allArgPatterns = [];
        foreach ($parameters as $parameter) {
            $paramName = $parameter->getName();
            $paramValue = $props->get($paramName);
            $formattedPattern = '';

            switch (true) {
                case is_string($paramValue):
                    $formattedPattern = "'" . preg_quote($paramValue, '/') . "'";
                    break;
                case is_array($paramValue):
                    $exportedArray = $this->exportArray($paramValue);
                    $formattedPattern = preg_quote($exportedArray, '/');
                    break;
                case is_bool($paramValue):
                    $formattedPattern = $paramValue ? 'true' : 'false';
                    break;
                case is_numeric($paramValue):
                    $formattedPattern = (string)$paramValue;
                    break;
                case $paramValue === null && $parameter->isOptional() && $parameter->isDefaultValueAvailable():
                    $formattedPattern = preg_quote((string)$parameter->getDefaultValue(), '/');
                    break;
                case $paramValue === null:
                    $formattedPattern = 'null';
                    break;
            }

            if ($formattedPattern !== '') {
                $allArgPatterns[] = $formattedPattern;
            }
        }

        // 2. Aplicamos la lógica de filtrado según las nuevas reglas
        $finalArgPatterns = [];
        $numArgs = count($allArgPatterns);

        if ($numArgs >= 2) {
            // Hay dos o más parámetros
            $uniqueArgs = array_unique($allArgPatterns);
            if (count($uniqueArgs) === 1) {
                // Si TODOS los parámetros son iguales, tomamos solo el primero.
                $finalArgPatterns = [$allArgPatterns[0]];
            } else {
                // Si son distintos, tomamos los primeros dos (comportamiento anterior).
                $finalArgPatterns = array_slice($allArgPatterns, 0, 2);
            }
        } else {
            // Si hay 0 o 1 parámetro, simplemente los usamos todos.
            $finalArgPatterns = $allArgPatterns;
        }

        // 3. Construimos el string final de argumentos
        $argsString = implode('\\s*,\\s*', $finalArgPatterns);

        // Retornamos el patrón completo
        return "$escapedClassName\\s*::\\s*$escapedMakerMethodName\\s*\\(\\s*$argsString\\s*\\)";
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
        ->setItems\((.*?)\)                     # Grupo 1: contenido dentro del setItems(...)
        \s* # posibles espacios o saltos de línea
        ->setEndBlock\(\s*[\'"]' . preg_quote($entityId, '/') . '[\'"]\s*\)   # ->setEndBlock("ID")
        /sx'; // ⚠️ 's' para que el punto incluya saltos de línea, 'x' para comentarios legibles
    }

    // funcion que recive un parametro un string y
    // retorna el patron que permite buscar rutas
    // en el archivo de rutas.
    private function getBlockPattern(FpEntityInterface $entity): string
    {
        $entityId = $entity->getId();
        // Genera el patrón regex de la llamada al método 'make' usando getMakerPattern
        $makerCallPattern = $this->getMakerPattern($entity);

        // ¡Importante!: No se necesita preg_quote aquí, ya que getMakerPattern
        // ya devuelve un patrón escapado para regex.
        // $escapedMakerCallPattern = preg_quote($makerCallPattern, '/'); // <-- ELIMINADO

        return '/
        ' . $makerCallPattern . '  # Class::make() (ya es un patrón regex flexible)
        .*?
        ->setEndBlock\(\s*[\'"]' . preg_quote($entityId, '/') . '[\'"]\s*\)
    /sx';
    }
}
