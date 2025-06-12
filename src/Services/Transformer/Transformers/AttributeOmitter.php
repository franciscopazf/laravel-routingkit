<?php

namespace Fp\FullRoute\Services\Transformer\Transformers;

use Countable;
use Closure;

class AttributeOmitter
{
    protected object $object;
    protected ?string $attribute = null;
    protected ?array $rules = null;

    private function __construct(object $object, ?array $rules = null, ?string $attribute = null)
    {
        $this->object = $object;
        $this->attribute = $attribute;

        if ($rules !== null) {
            $this->rules = $rules;
        } elseif ($this->attribute !== null) {
            if (method_exists($this->object, 'getOmmittedAttributes')) {
                $allObjectRules = $this->object->getOmmittedAttributes();
                $this->rules = array_key_exists($this->attribute, $allObjectRules)
                    ? $allObjectRules[$this->attribute]
                    : [];
            } else {
                $this->rules = [];
            }
        } else {
            $this->rules = [];
        }
    }

    public static function make(object $object, ?array $rules = null, ?string $attribute = null): self
    {
        return new self($object, $rules, $attribute);
    }

    public function setAttribute(string $attribute): self
    {
        $this->attribute = $attribute;

        if (method_exists($this->object, 'getOmmittedAttributes')) {
            $allObjectRules = $this->object->getOmmittedAttributes();
            $this->rules = array_key_exists($attribute, $allObjectRules) ? $allObjectRules[$attribute] : [];
        } else {
            $this->rules = [];
        }

        return $this;
    }

    /**
     * Valida si el atributo debe ser omitido basándose en las reglas.
     *
     * @return bool Retorna true si el atributo debe ser omitido (al menos una regla se cumple), false en caso contrario.
     */
    public function validate(): bool
    {
        // Si el valor del atributo principal es null, lo omitimos por defecto
        if ($this->attribute !== null && $this->getAttributeValue($this->attribute) === null) {
            return true;
        }

        if ($this->rules === null || empty($this->rules)) {
            return false; // No hay reglas para validar, no se omite por defecto.
        }

        // Evalúa las reglas. Si al menos una retorna true, el atributo debe ser omitido.
        return $this->evaluateRules($this->rules, $this->object, $this->attribute);
    }

    /**
     * Evalúa un conjunto de reglas.
     *
     * @param array $rules El array de reglas a evaluar.
     * @param mixed $context El contexto actual para la evaluación de la regla (puede ser el objeto o el valor de un atributo).
     * @param string|null $attributeToEvaluate El nombre del atributo actualmente bajo evaluación si aplica.
     * @return bool Retorna true si al menos una regla se cumple, false en caso contrario.
     */
    protected function evaluateRules(array $rules, mixed $context, ?string $attributeToEvaluate = null): bool
    {
        foreach ($rules as $ruleKey => $ruleValue) {
            $ruleResult = false;

            // Manejar reglas anidadas
            if (is_string($ruleKey) && is_array($ruleValue)) {
                // Si la "gate" rule se cumple, entonces evaluamos las reglas anidadas.
                // En este contexto, si la gate rule se cumple Y AL MENOS UNA de las reglas anidadas se cumple,
                // entonces la regla compuesta se cumple.
                if ($this->processSingleRule($ruleKey, $context, $attributeToEvaluate)) {
                    // Aquí, si la gate rule es TRUE, la evaluación de las sub-reglas debería ser un OR.
                    // Si evaluateRules con las sub-reglas retorna TRUE, entonces ya tenemos una regla cumplida.
                    if ($this->evaluateRules($ruleValue, $context, $attributeToEvaluate)) {
                        return true; // Una sub-regla anidada se cumplió después de la gate rule.
                    }
                }
            } elseif (is_string($ruleKey) || is_numeric($ruleKey)) {
                $rule = is_string($ruleKey) ? $ruleKey : $ruleValue;

                if ($rule instanceof Closure) {
                    $valueForClosure = ($attributeToEvaluate !== null)
                        ? $this->getAttributeValue($attributeToEvaluate)
                        : $context;
                    try {
                        $ruleResult = $rule($valueForClosure, $context);
                    } catch (\Throwable $e) {
                        $ruleResult = false;
                    }
                } elseif (is_string($rule)) {
                    $ruleResult = $this->processSingleRule($rule, $context, $attributeToEvaluate);
                }
            }

            // Si cualquier regla individual (no anidada, o el resultado final de una anidada) se cumple,
            // entonces podemos retornar true inmediatamente (lógica OR).
            if ($ruleResult) {
                return true;
            }
        }

        // Si llegamos aquí, significa que ninguna de las reglas se cumplió.
        return false;
    }


    /**
     * Procesa una única regla de cadena (ej. 'omit', 'same:id', 'greater_than:count:100').
     *
     * @param string $rule La regla a procesar.
     * @param mixed $context El contexto actual (objeto o valor del atributo).
     * @param string|null $attributeToEvaluate El nombre del atributo si se está evaluando uno.
     * @return bool
     */
    protected function processSingleRule(string $rule, mixed $context, ?string $attributeToEvaluate = null): bool
    {
        $parts = explode(':', $rule);
        $ruleName = array_shift($parts);
        $parameters = $parts;

        $methodName = 'rule' . ucfirst($ruleName);

        if (method_exists($this, $methodName)) {
            return $this->$methodName(...$parameters);
        }

        return false;
    }


    /**
     * Omite el atributo si su valor es el parámetro especificado.
     * Uso: 'omit', 'omit:true', 'omit:false', 'omit:some_string'
     *
     * @param string|null $parameter El valor a comparar, si se proporciona.
     * @return bool
     */
    protected function ruleOmit(?string $parameter = null): bool
    {
        if ($this->attribute === null) {
            return true;
        }

        $attributeValue = $this->getAttributeValue($this->attribute);

        if ($parameter === null) {
            return $attributeValue !== null;
        }

        $parsedParameter = $this->parseParameter($parameter);

        return $attributeValue === $parsedParameter;
    }

    /**
     * Omite el atributo si el valor del atributo principal es igual al valor de otro atributo.
     * Uso: 'same:other_attribute'
     *
     * @param string $otherAttributePath La ruta del otro atributo a comparar.
     * @return bool
     */
    protected function ruleSame(string $otherAttributePath): bool
    {
        if ($this->attribute === null) {
            return false;
        }

        $currentValue = $this->getAttributeValue($this->attribute);
        $otherValue = $this->getAttributeValue($otherAttributePath);

        return $currentValue === $otherValue && $currentValue !== null;
    }

    /**
     * Omite si el objeto tiene hijos (basado en un atributo 'items').
     * Uso: 'haschildren'
     *
     * @return bool
     */
    protected function ruleHaschildren(): bool
    {
        $items = $this->getAttributeValue('items');
        return (is_array($items) || $items instanceof Countable) && count($items) > 0;
    }

    /**
     * Omite el atributo si el valor de un atributo dado es estrictamente igual a un valor específico.
     * Uso: 'equals:other_attribute:value'
     *
     * @param string $attributePath La ruta del atributo a evaluar.
     * @param string $expectedValue El valor esperado para la comparación.
     * @return bool
     */
    protected function ruleEquals(string $attributePath, string $expectedValue): bool
    {
        $value = $this->getAttributeValue($attributePath);
        return $value === $this->parseParameter($expectedValue);
    }

    /**
     * Omite el atributo si el valor de un atributo dado NO es estrictamente igual a un valor específico.
     * Uso: 'not_equals:other_attribute:value'
     *
     * @param string $attributePath La ruta del atributo a evaluar.
     * @param string $unexpectedValue El valor que NO se espera para la comparación.
     * @return bool
     */
    protected function ruleNotEquals(string $attributePath, string $unexpectedValue): bool
    {
        $value = $this->getAttributeValue($attributePath);
        return $value !== $this->parseParameter($unexpectedValue);
    }

    /**
     * Omite el atributo si el valor de un atributo dado es mayor que un valor específico.
     * Uso: 'greater_than:other_attribute:100'
     *
     * @param string $attributePath La ruta del atributo a evaluar.
     * @param string $thresholdValue El valor numérico de umbral.
     * @return bool
     */
    protected function ruleGreaterThan(string $attributePath, string $thresholdValue): bool
    {
        $value = $this->getAttributeValue($attributePath);
        $threshold = $this->parseParameter($thresholdValue);

        return is_numeric($value) && is_numeric($threshold) && $value > $threshold;
    }

    /**
     * Omite el atributo si el valor de un atributo dado es menor que un valor específico.
     * Uso: 'less_than:other_attribute:50'
     *
     * @param string $attributePath La ruta del atributo a evaluar.
     * @param string $thresholdValue El valor numérico de umbral.
     * @return bool
     */
    protected function ruleLessThan(string $attributePath, string $thresholdValue): bool
    {
        $value = $this->getAttributeValue($attributePath);
        $threshold = $this->parseParameter($thresholdValue);

        return is_numeric($value) && is_numeric($threshold) && $value < $threshold;
    }

    /**
     * Omite el atributo si un atributo dado existe en el objeto (es resolvable su path).
     * Uso: 'has_attribute:some.nested.property'
     *
     * @param string $attributePath La ruta del atributo a verificar si existe.
     * @return bool
     */
    protected function ruleHasAttribute(string $attributePath): bool
    {
        return $this->pathResolvable($attributePath);
    }

    /**
     * Omite el atributo si el valor de un atributo dado es estrictamente true.
     * Uso: 'is_true:other_boolean_attribute'
     *
     * @param string $attributePath La ruta del atributo booleano a evaluar.
     * @return bool
     */
    protected function ruleIsTrue(string $attributePath): bool
    {
        return $this->getAttributeValue($attributePath) === true;
    }

    /**
     * Omite el atributo si el valor de un atributo dado es estrictamente false.
     * Uso: 'is_false:other_boolean_attribute'
     *
     * @param string $attributePath La ruta del atributo booleano a evaluar.
     * @return bool
     */
    protected function ruleIsFalse(string $attributePath): bool
    {
        return $this->getAttributeValue($attributePath) === false;
    }

    /**
     * Omite el atributo si el arreglo del atributo principal tiene menos de la cantidad mínima de elementos.
     * Uso: 'min_elements:3' (Omite si el arreglo tiene menos de 3 elementos)
     *
     * @param string $minCount El número mínimo de elementos requeridos.
     * @return bool
     */
    protected function ruleMinElements(string $minCount): bool
    {
        if ($this->attribute === null) {
            return false;
        }

        $value = $this->getAttributeValue($this->attribute);
        $requiredCount = (int) $this->parseParameter($minCount);

        // Si el valor no es un arreglo o no es contable, asumimos que tiene 0 elementos o que no se aplica la regla.
        // Si es contable, verificamos si su conteo es menor al mínimo requerido.
        return (is_array($value) || $value instanceof Countable) && count($value) < $requiredCount;
    }

    /**
     * Parsea un string para convertirlo a su tipo de datos real (booleano, int, float).
     *
     * @param string $param El parámetro a parsear.
     * @return mixed El valor parseado.
     */
    protected function parseParameter(string $param): mixed
    {
        if ($param === 'true') {
            return true;
        }
        if ($param === 'false') {
            return false;
        }
        if (is_numeric($param)) {
            return (strpos($param, '.') !== false) ? (float) $param : (int) $param;
        }
        return $param;
    }

    protected function getAttributeValue(string $attributePath): mixed
    {
        $parts = explode('.', $attributePath);
        $currentValue = $this->object;

        foreach ($parts as $part) {
            if (str_ends_with($part, '()')) {
                $methodName = rtrim($part, '()');
                if (is_object($currentValue) && method_exists($currentValue, $methodName)) {
                    try {
                        $currentValue = $currentValue->$methodName();
                    } catch (\Throwable $e) {
                        return null;
                    }
                } else {
                    return null;
                }
            } else {
                if (is_object($currentValue)) {
                    if (property_exists($currentValue, $part)) {
                        $currentValue = $currentValue->$part;
                    } elseif (method_exists($currentValue, 'get' . ucfirst($part))) {
                        try {
                            $currentValue = $currentValue->{'get' . ucfirst($part)}();
                        } catch (\Throwable $e) {
                            return null;
                        }
                    } else {
                        return null;
                    }
                } else {
                    return null;
                }
            }
        }

        return $currentValue;
    }

    protected function pathResolvable(string $attributePath): bool
    {
        $parts = explode('.', $attributePath);
        $currentObject = $this->object;

        foreach ($parts as $part) {
            if (is_object($currentObject)) {
                if (str_ends_with($part, '()')) {
                    $methodName = rtrim($part, '()');
                    if (method_exists($currentObject, $methodName)) {
                        try {
                            $currentObject = $currentObject->$methodName();
                        } catch (\Throwable $e) {
                            return false;
                        }
                    } else {
                        return false;
                    }
                } else {
                    if (property_exists($currentObject, $part)) {
                        $currentObject = $currentObject->$part;
                    } elseif (method_exists($currentObject, 'get' . ucfirst($part))) {
                        try {
                            $currentObject = $currentObject->{'get' . ucfirst($part)}();
                        } catch (\Throwable $e) {
                            return false;
                        }
                    } else {
                        return false;
                    }
                }
            } else {
                return false;
            }
        }
        return true;
    }

    protected function attributeExists(string $attributePath): bool
    {
        return $this->pathResolvable($attributePath);
    }
}