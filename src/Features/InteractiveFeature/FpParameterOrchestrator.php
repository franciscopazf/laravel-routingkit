<?php

namespace Fp\RoutingKit\Features\InteractiveFeature;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Fp\RoutingKit\Entities\FpRoute;
use Fp\RoutingKit\Entities\FpNavigation; // Importa FpNavigation

class FpParameterOrchestrator
{
    public static function make(): static
    {
        return new static();
    }

    /**
     * Procesa un conjunto de parámetros, validando y completando los faltantes
     * utilizando Laravel Prompts y reglas de validación dinámicas.
     *
     * @param array $initialParameters Los parámetros iniciales que se tienen.
     * @param array $parameterDefinitions Las definiciones de los parámetros esperados.
     * @return array Los parámetros finales, validados y/o completados.
     * @throws \Exception Si faltan opciones 'in' para arrays.
     */
    public function processParameters(array $initialParameters, array $parameterDefinitions): array
    {
        $finalParameters = $initialParameters;

        foreach ($parameterDefinitions as $key => $definition) {
            $value = $initialParameters[$key] ?? null; // Obtiene el valor inicial si existe

            $rules = $definition['rules'] ?? [];
            $description = $definition['description'] ?? 'Valor para ' . $key;
            $type = $definition['type'] ?? 'string';
            $closureForPromptOrValue = $definition['closure'] ?? null;

            // --- PRE-PROCESAMIENTO: Asegurar que el valor inicial coincida con el tipo esperado ---
            // Si hay un valor inicial, verificar su tipo según la definición.
            // Si el tipo no coincide, forzar el valor a null para que se pida de nuevo.
            if ($value !== null) {
                if ($type === 'array_multiple' && !is_array($value)) {
                    \Laravel\Prompts\warning("El valor inicial para '{$key}' no es un array. Se solicitará de nuevo.");
                    $value = null;
                } elseif ($type === 'array_unique' && !is_string($value)) {
                    \Laravel\Prompts\warning("El valor inicial para '{$key}' no es una cadena. Se solicitará de nuevo.");
                    $value = null;
                } elseif ($type === 'boolean' && !is_bool($value)) {
                    \Laravel\Prompts\warning("El valor inicial para '{$key}' no es un booleano. Se solicitará de nuevo.");
                    $value = null;
                } elseif (($type === 'string' || $type === 'string_select') && !is_string($value)) {
                    \Laravel\Prompts\warning("El valor inicial para '{$key}' no es una cadena. Se solicitará de nuevo.");
                    $value = null;
                }
            }
            // --- FIN PRE-PROCESAMIENTO ---

            // Loop para asegurar la validez del parámetro, incluyendo la lógica de expect_false
            do {
                $isValid = true;
                $expectFalseFailed = false;
                $validationErrors = [];

                // Determinar si necesitamos pedir el valor.
                $promptNeeded = ($value === null || $expectFalseFailed || !empty($validationErrors));

                // --- Lógica de Prompts y Obtención de Valores ---
                $promptOptions = [
                    'label' => $description,
                    'required' => in_array('required', $rules),
                ];

                if ($type === 'array_multiple') {
                    if ($promptNeeded) {
                        $options = $this->getArrayOptionsFromRules($rules);
                        if (empty($options)) {
                            throw new \Exception("Para 'type: {$type}', se requiere una regla 'in:option1,option2' en las reglas del parámetro '{$key}'.");
                        }
                        $promptOptions['options'] = $options;
                        $promptOptions['default'] = (array)($initialParameters[$key] ?? []);
                        $value = \Laravel\Prompts\multiselect(...$promptOptions);
                    }
                } elseif ($type === 'array_unique') {
                    if ($promptNeeded) {
                        $options = $this->getArrayOptionsFromRules($rules);
                        if (empty($options)) {
                            throw new \Exception("Para 'type: {$type}', se requiere una regla 'in:option1,option2' en las reglas del parámetro '{$key}'.");
                        }
                        $promptOptions['options'] = $options;
                        $promptOptions['default'] = (string)($initialParameters[$key] ?? null);
                        $value = \Laravel\Prompts\select(...$promptOptions);
                    }
                } elseif ($type === 'boolean') {
                    if ($promptNeeded) {
                        $defaultBool = false;
                        if (is_callable($closureForPromptOrValue)) {
                            $closureResult = $closureForPromptOrValue($finalParameters); // Pasar $finalParameters
                            if (is_bool($closureResult)) {
                                $defaultBool = $closureResult;
                            }
                        }
                        $promptOptions['default'] = (bool)($initialParameters[$key] ?? $defaultBool);
                        $value = \Laravel\Prompts\confirm(...$promptOptions);
                    }
                } elseif ($type === 'string_select') {
                    if (is_callable($closureForPromptOrValue)) {
                        $closureResult = $closureForPromptOrValue($finalParameters); // Pasar $finalParameters

                        if (is_string($closureResult) && ($closureResult === FpFileBrowser::CANCEL_SIGNAL || $closureResult === FpFileBrowser::BACK_TO_ROOT_SELECTION_SIGNAL)) {
                            $value = null;
                        } elseif (is_string($closureResult) && !empty($closureResult)) {
                            $value = $closureResult;
                            \Laravel\Prompts\info("Seleccionado automáticamente para '{$key}': " . $value);
                        } elseif (is_array($closureResult)) {
                            $filteredOptions = array_filter($closureResult, 'is_string');
                            if (count($filteredOptions) === 1) {
                                $value = array_values($filteredOptions)[0];
                                \Laravel\Prompts\info("Seleccionado automáticamente para '{$key}' (única opción): " . $value);
                            } elseif (count($filteredOptions) > 1) {
                                if ($promptNeeded) {
                                    $promptOptions['options'] = array_combine($filteredOptions, $filteredOptions);
                                    $promptOptions['default'] = (string)($initialParameters[$key] ?? $value ?? '');
                                    $value = \Laravel\Prompts\select(...$promptOptions);
                                }
                            } else {
                                \Laravel\Prompts\warning("El closure para '{$key}' no retornó opciones válidas para STRING_SELECT.");
                                $value = null;
                            }
                        } else {
                            \Laravel\Prompts\warning("El closure para '{$key}' retornó un tipo de dato inesperado para STRING_SELECT.");
                            $value = null;
                        }
                    } else {
                        \Laravel\Prompts\error("El tipo 'string_select' requiere un 'closure' que retorne el valor o un array de opciones.");
                        $value = null;
                    }
                } elseif (!empty($this->getSelectOptionsFromRules($rules))) {
                    if ($promptNeeded) {
                        $promptOptions['options'] = $this->getSelectOptionsFromRules($rules);
                        $promptOptions['default'] = (string)($initialParameters[$key] ?? '');
                        $value = \Laravel\Prompts\select(...$promptOptions);
                    }
                } else { // Entrada de texto estándar
                    if ($promptNeeded) {
                        // Si hay closure, se ejecuta para el valor por defecto.
                        $defaultText = (is_callable($closureForPromptOrValue) && $type === 'string')
                            ? $closureForPromptOrValue($finalParameters) // Pasar $finalParameters
                            : null;

                        $promptOptions['placeholder'] = 'Ingresa el valor para ' . $key;
                        $promptOptions['default'] = (string)($initialParameters[$key] ?? $defaultText ?? '');
                        $value = \Laravel\Prompts\text(...$promptOptions);
                    }
                }

                // --- Manejo de la regla 'expect_false' ---
                $expectFalseClosure = $rules['expect_false'] ?? null;
                if (is_callable($expectFalseClosure)) {
                    // Pass $value and $finalParameters to the expect_false closure
                    if ($value !== null && $expectFalseClosure($value, $finalParameters)) {
                        $isValid = false;
                        $expectFalseFailed = true;
                        \Laravel\Prompts\error('El valor ingresado ya existe o no cumple la condición de unicidad. Por favor, intenta con un valor diferente.');
                        \Laravel\Prompts\info("Valor actual para '$key': " . ($value ?? 'Vacío'));
                        $value = null;
                        continue;
                    }
                }

                // --- Validación estándar con Laravel Validator para las demás reglas ---
                $validationRulesForLaravel = collect($rules)->except('expect_false')->toArray();

                // Si el valor es null y es requerido
                if ($value === null && in_array('required', $validationRulesForLaravel)) {
                     $isValid = false;
                }
                
                // Solo validar si el valor no es nulo O si la regla 'nullable' está presente
                $shouldValidate = ($value !== null || in_array('nullable', $validationRulesForLaravel));
                if ($shouldValidate) {
                    $dataToValidate = [$key => $value];
                    $validator = Validator::make($dataToValidate, [$key => $validationRulesForLaravel]);

                    if ($validator->fails()) {
                        $isValid = false;
                        $validationErrors = $validator->errors()->get($key);
                        foreach ($validationErrors as $error) {
                            \Laravel\Prompts\error($error);
                        }
                        \Laravel\Prompts\info("Por favor, corrige el valor para '$key'.");
                        $value = null;
                    } else {
                        // Asegurar el tipo de dato final
                        switch ($type) {
                            case 'array_unique':
                                $value = (string)$value;
                                break;
                            case 'array_multiple':
                                $value = (array)$value;
                                break;
                            case 'boolean':
                                $value = (bool)$value;
                                break;
                            case 'string':
                            case 'string_select':
                            default:
                                $value = (string)$value;
                                break;
                        }
                        $finalParameters[$key] = $value;
                    }
                } else {
                    // Si el valor es null y no es requerido, se considera válido
                    $finalParameters[$key] = $value;
                }

            } while (!$isValid || ($value === null && in_array('required', $rules))); // Repetir hasta que el valor sea válido y pase todas las reglas
        }

        return $finalParameters;
    }

    /**
     * Extrae las opciones de un arreglo de reglas 'in:...' para Laravel Prompts multiselect o select.
     *
     * @param array $rules
     * @return array
     */
    protected function getArrayOptionsFromRules(array $rules): array
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'in:')) {
                return explode(',', substr($rule, 3));
            } elseif ($rule instanceof Rule && method_exists($rule, 'getValues')) {
                // Si la regla 'in' se pasa como objeto Rule::in([...])
                return $rule->getValues();
            }
        }
        return [];
    }

    /**
     * Extrae las opciones de un arreglo de reglas 'in:...' para Laravel Prompts select.
     * (Reutiliza la misma lógica que getArrayOptionsFromRules, pero la mantenemos separada para claridad si en el futuro se desea diferenciar)
     *
     * @param array $rules
     * @return array
     */
    protected function getSelectOptionsFromRules(array $rules): array
    {
        return $this->getArrayOptionsFromRules($rules);
    }
}
