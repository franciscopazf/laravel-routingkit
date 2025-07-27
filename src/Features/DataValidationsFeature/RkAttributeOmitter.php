<?php

namespace Rk\RoutingKit\Features\DataValidationsFeature;

use Countable;
use Closure;

class RkAttributeOmitter
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

    public function validate(): bool
    {
        if ($this->attribute !== null && is_object($this->getAttributeValue($this->attribute))) {
            return true;
        }

        if ($this->attribute !== null && $this->getAttributeValue($this->attribute) === null) {
            return true;
        }

        if ($this->rules === null || empty($this->rules)) {
            return false;
        }

        return $this->evaluateRules($this->rules, $this->object, $this->attribute);
    }

    protected function evaluateRules(array $rules, mixed $context, ?string $attributeToEvaluate = null): bool
    {
        foreach ($rules as $ruleKey => $ruleValue) {
            $ruleResult = false;

            if (is_string($ruleKey) && is_array($ruleValue)) {
                if ($this->processSingleRule($ruleKey, $context, $attributeToEvaluate)) {
                    if ($this->evaluateRules($ruleValue, $context, $attributeToEvaluate)) {
                        return true;
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

            if ($ruleResult) {
                return true;
            }
        }

        return false;
    }

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

    protected function ruleOmit(?string $parameter = null): bool
    {
        if ($this->attribute === null) return true;

        $attributeValue = $this->getAttributeValue($this->attribute);

        if ($parameter === null) return $attributeValue !== null;

        $parsedParameter = $this->parseParameter($parameter);

        return $attributeValue === $parsedParameter;
    }

    protected function ruleSame(string $otherAttributePath): bool
    {
        if ($this->attribute === null) return false;

        $currentValue = $this->getAttributeValue($this->attribute);
        $otherValue = $this->getAttributeValue($otherAttributePath);

        return $currentValue === $otherValue && $currentValue !== null;
    }

    protected function ruleHaschildren(): bool
    {
        $items = $this->getAttributeValue('items');
        return (is_array($items) || $items instanceof Countable) && count($items) > 0;
    }

    protected function ruleEquals(string $attributePath, string $expectedValue): bool
    {
        $value = $this->getAttributeValue($attributePath);
        return $value === $this->parseParameter($expectedValue);
    }

    protected function ruleNotEquals(string $attributePath, string $unexpectedValue): bool
    {
        $value = $this->getAttributeValue($attributePath);
        return $value !== $this->parseParameter($unexpectedValue);
    }

    protected function ruleGreaterThan(string $attributePath, string $thresholdValue): bool
    {
        $value = $this->getAttributeValue($attributePath);
        $threshold = $this->parseParameter($thresholdValue);

        return is_numeric($value) && is_numeric($threshold) && $value > $threshold;
    }

    protected function ruleLessThan(string $attributePath, string $thresholdValue): bool
    {
        $value = $this->getAttributeValue($attributePath);
        $threshold = $this->parseParameter($thresholdValue);

        return is_numeric($value) && is_numeric($threshold) && $value < $threshold;
    }

    protected function ruleHasAttribute(string $attributePath): bool
    {
        return $this->pathResolvable($attributePath);
    }

    protected function ruleIsTrue(string $attributePath): bool
    {
        return $this->getAttributeValue($attributePath) === true;
    }

    protected function ruleIsFalse(string $attributePath): bool
    {
        return $this->getAttributeValue($attributePath) === false;
    }

    protected function ruleMinElements(string $minCount): bool
    {
        if ($this->attribute === null) return false;

        $value = $this->getAttributeValue($this->attribute);
        $requiredCount = (int) $this->parseParameter($minCount);

        return (is_array($value) || $value instanceof Countable) && count($value) < $requiredCount;
    }

    protected function ruleIsBlank(): bool
    {
        if ($this->attribute === null) return true;

        $value = $this->getAttributeValue($this->attribute);

        return $value === null || (is_string($value) && trim($value) === '');
    }

    protected function parseParameter(string $param): mixed
    {
        if ($param === 'true') return true;
        if ($param === 'false') return false;
        if ($param === 'empty' || $param === '""') return '';
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
