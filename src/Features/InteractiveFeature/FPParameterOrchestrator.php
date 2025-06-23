<?php

namespace FP\RoutingKit\Features\InteractiveFeature;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FPParameterOrchestrator
{
    public static function make(): static
    {
        return new static();
    }

    public function processParameters(array $initialParameters, array $parameterDefinitions): array
    {
        $finalParameters = $initialParameters;

        foreach ($parameterDefinitions as $key => $definition) {
            $value = $initialParameters[$key] ?? null;

            $rules = $definition['rules'] ?? [];
            $description = $definition['description'] ?? 'Valor para ' . $key;
            $type = $definition['type'] ?? 'string';
            $closureForPromptOrValue = $definition['closure'] ?? null;

            $value = $this->preprocessInitialValue($value, $type, $key);

            if ($this->validateEarlyValue($value, $rules, $key, $finalParameters)) {
                $finalParameters[$key] = $value;
                continue;
            }

            $value = $this->promptUntilValid($value, $rules, $description, $type, $key, $closureForPromptOrValue, $initialParameters, $finalParameters);

            $finalParameters[$key] = $value;
        }

        return $finalParameters;
    }

    protected function preprocessInitialValue($value, string $type, string $key)
    {
        if ($value === null) {
            return null;
        }

        if ($type === 'array_multiple' && !is_array($value)) {
            \Laravel\Prompts\warning("El valor inicial para '{$key}' no es un array. Se solicitará de nuevo.");
            return null;
        }

        if ($type === 'array_unique' && !is_string($value)) {
            \Laravel\Prompts\warning("El valor inicial para '{$key}' no es una cadena. Se solicitará de nuevo.");
            return null;
        }

        if ($type === 'boolean' && !is_bool($value)) {
            \Laravel\Prompts\warning("El valor inicial para '{$key}' no es un booleano. Se solicitará de nuevo.");
            return null;
        }

        if (($type === 'string' || $type === 'string_select') && !is_string($value)) {
            \Laravel\Prompts\warning("El valor inicial para '{$key}' no es una cadena. Se solicitará de nuevo.");
            return null;
        }

        return $value;
    }

    protected function validateEarlyValue($value, array $rules, string $key, array $finalParameters): bool
    {
        if ($value === null) {
            return false;
        }

        $validationRulesForLaravel = collect($rules)->except('expect_false')->toArray();
        $validator = Validator::make([$key => $value], [$key => $validationRulesForLaravel]);

        $expectFalseClosure = $rules['expect_false'] ?? null;

        $passesValidator = !$validator->fails();
        $passesExpectFalse = !is_callable($expectFalseClosure) || !$expectFalseClosure($value, $finalParameters);

        return $passesValidator && $passesExpectFalse;
    }

    protected function promptUntilValid($value, array $rules, string $description, string $type, string $key, $closureForPromptOrValue, array $initialParameters, array &$finalParameters)
    {
        do {
            $promptNeeded = ($value === null);

            $promptOptions = [
                'label' => $description,
                'required' => in_array('required', $rules),
            ];

            if ($type === 'array_multiple') {
                $value = $this->handleArrayMultiplePrompt($value, $promptNeeded, $rules, $promptOptions, $initialParameters, $key);
            } elseif ($type === 'array_unique') {
                $value = $this->handleArrayUniquePrompt($value, $promptNeeded, $rules, $promptOptions, $initialParameters, $key);
            } elseif ($type === 'boolean') {
                $value = $this->handleBooleanPrompt($value, $promptNeeded, $closureForPromptOrValue, $promptOptions, $initialParameters, $key, $finalParameters);
            } elseif ($type === 'string_select') {
                $value = $this->handleStringSelectPrompt($value, $promptNeeded, $closureForPromptOrValue, $promptOptions, $initialParameters, $key, $finalParameters);
            } elseif (!empty($this->getSelectOptionsFromRules($rules))) {
                $value = $this->handleSelectFromRulesPrompt($value, $promptNeeded, $rules, $promptOptions, $initialParameters, $key);
            } else {
                $value = $this->handleTextPrompt($value, $promptNeeded, $closureForPromptOrValue, $promptOptions, $initialParameters, $key, $type, $finalParameters);
            }

            if ($this->handleExpectFalseRule($value, $rules, $finalParameters, $key)) {
                $value = null;
                continue;
            }

            if (!$this->validateValue($value, $rules, $key)) {
                $value = null;
                continue;
            }

            $value = $this->castValueByType($value, $type);

            $finalParameters[$key] = $value;

            // Sale del ciclo si pasó la validación
            break;

        } while (true);

        return $value;
    }

    protected function handleArrayMultiplePrompt($value, bool $promptNeeded, array $rules, array $promptOptions, array $initialParameters, string $key)
    {
        if ($promptNeeded) {
            $options = $this->getArrayOptionsFromRules($rules);
            if (empty($options)) {
                throw new \Exception("Para 'type: array_multiple', se requiere una regla 'in:option1,option2' en las reglas del parámetro '{$key}'.");
            }
            $promptOptions['options'] = $options;
            $promptOptions['default'] = (array)($initialParameters[$key] ?? []);
            $value = \Laravel\Prompts\multiselect(...$promptOptions);
        }
        return $value;
    }

    protected function handleArrayUniquePrompt($value, bool $promptNeeded, array $rules, array $promptOptions, array $initialParameters, string $key)
    {
        if ($promptNeeded) {
            $options = $this->getArrayOptionsFromRules($rules);
            if (empty($options)) {
                throw new \Exception("Para 'type: array_unique', se requiere una regla 'in:option1,option2' en las reglas del parámetro '{$key}'.");
            }
            $promptOptions['options'] = $options;
            $promptOptions['default'] = (string)($initialParameters[$key] ?? null);
            $value = \Laravel\Prompts\select(...$promptOptions);
        }
        return $value;
    }

    protected function handleBooleanPrompt($value, bool $promptNeeded, $closureForPromptOrValue, array $promptOptions, array $initialParameters, string $key, array $finalParameters)
    {
        if ($promptNeeded) {
            $defaultBool = false;
            if (is_callable($closureForPromptOrValue)) {
                $closureResult = $closureForPromptOrValue($finalParameters);
                if (is_bool($closureResult)) {
                    $defaultBool = $closureResult;
                }
            }
            $promptOptions['default'] = (bool)($initialParameters[$key] ?? $defaultBool);
            $value = \Laravel\Prompts\confirm(...$promptOptions);
        }
        return $value;
    }

    protected function handleStringSelectPrompt($value, bool $promptNeeded, $closureForPromptOrValue, array $promptOptions, array $initialParameters, string $key, array $finalParameters)
    {
        if (!is_callable($closureForPromptOrValue)) {
            \Laravel\Prompts\error("El tipo 'string_select' requiere un 'closure' que retorne el valor o un array de opciones.");
            return null;
        }

        $closureResult = $closureForPromptOrValue($finalParameters);

        if (is_string($closureResult) && ($closureResult === FPileBrowser::CANCEL_SIGNAL || $closureResult === FPileBrowser::BACK_TO_ROOT_SELECTION_SIGNAL)) {
            return null;
        } elseif (is_string($closureResult) && !empty($closureResult)) {
            \Laravel\Prompts\info("Seleccionado automáticamente para '{$key}': " . $closureResult);
            return $closureResult;
        } elseif (is_array($closureResult)) {
            $filteredOptions = array_filter($closureResult, 'is_string');
            if (count($filteredOptions) === 1) {
                \Laravel\Prompts\info("Seleccionado automáticamente para '{$key}' (única opción): " . array_values($filteredOptions)[0]);
                return array_values($filteredOptions)[0];
            } elseif (count($filteredOptions) > 1 && $promptNeeded) {
                $promptOptions['options'] = array_combine($filteredOptions, $filteredOptions);
                $promptOptions['default'] = (string)($initialParameters[$key] ?? $value ?? '');
                return \Laravel\Prompts\select(...$promptOptions);
            } else {
                \Laravel\Prompts\warning("El closure para '{$key}' no retornó opciones válidas para STRING_SELECT.");
                return null;
            }
        } else {
            \Laravel\Prompts\warning("Se selecciono un valor Nulo para el parámetro: '{$key}' ");
            return null;
        }
    }

    protected function handleSelectFromRulesPrompt($value, bool $promptNeeded, array $rules, array $promptOptions, array $initialParameters, string $key)
    {
        if ($promptNeeded) {
            $promptOptions['options'] = $this->getSelectOptionsFromRules($rules);
            $promptOptions['default'] = (string)($initialParameters[$key] ?? '');
            $value = \Laravel\Prompts\select(...$promptOptions);
        }
        return $value;
    }

    protected function handleTextPrompt($value, bool $promptNeeded, $closureForPromptOrValue, array $promptOptions, array $initialParameters, string $key, string $type, array $finalParameters)
    {
        if ($promptNeeded) {
            $defaultText = (is_callable($closureForPromptOrValue) && $type === 'string')
                ? $closureForPromptOrValue($finalParameters)
                : null;

            $promptOptions['placeholder'] = 'Ingresa el valor para ' . $key;
            $promptOptions['default'] = (string)($initialParameters[$key] ?? $defaultText ?? '');
            $value = \Laravel\Prompts\text(...$promptOptions);
        }
        return $value;
    }

    protected function handleExpectFalseRule($value, array $rules, array $finalParameters, string $key): bool
    {
        $expectFalseClosure = $rules['expect_false'] ?? null;
        if (is_callable($expectFalseClosure)) {
            if ($value !== null && $expectFalseClosure($value, $finalParameters)) {
                \Laravel\Prompts\error('El valor ingresado ya existe o no cumple la condición de unicidad. Por favor, intenta con un valor diferente.');
                \Laravel\Prompts\info("Valor actual para '$key': " . ($value ?? 'Vacío'));
                return true;
            }
        }
        return false;
    }

    protected function validateValue($value, array $rules, string $key): bool
    {
        $validationRulesForLaravel = collect($rules)->except('expect_false')->toArray();

        if ($value === null && in_array('required', $validationRulesForLaravel)) {
            return false;
        }

        $shouldValidate = ($value !== null || in_array('nullable', $validationRulesForLaravel));

        if ($shouldValidate) {
            $validator = Validator::make([$key => $value], [$key => $validationRulesForLaravel]);
            if ($validator->fails()) {
                $errors = $validator->errors()->get($key);
                foreach ($errors as $error) {
                    \Laravel\Prompts\error($error);
                }
                \Laravel\Prompts\info("Por favor, corrige el valor para '$key'.");
                return false;
            }
        }
        return true;
    }

    protected function castValueByType($value, string $type)
    {
        switch ($type) {
            case 'array_unique':
                return (string)$value;
            case 'array_multiple':
                return (array)$value;
            case 'boolean':
                return (bool)$value;
            case 'string':
            case 'string_select':
            default:
                return (string)$value;
        }
    }

    protected function getArrayOptionsFromRules(array $rules): array
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'in:')) {
                return explode(',', substr($rule, 3));
            } elseif ($rule instanceof Rule && method_exists($rule, 'getValues')) {
                return $rule->getValues();
            }
        }
        return [];
    }

    protected function getSelectOptionsFromRules(array $rules): array
    {
        return $this->getArrayOptionsFromRules($rules);
    }
}
