<?php

namespace Fp\RoutingKit\Features\InteractiveFeature;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Fp\RoutingKit\Entities\FpRoute; // Asumo que FpRoute está en Entities

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
            $closureForPromptOrValue = $definition['closure'] ?? null; // Renombrado para mayor claridad

            // Loop para asegurar la validez del parámetro, incluyendo la lógica de expect_false
            do {
                $isValid = true;
                $expectFalseFailed = false; // Bandera para indicar si expect_false falló
                $validationErrors = []; // Resetear errores de validación en cada intento

                // Determinar si necesitamos pedir el valor
                $promptNeeded = ($value === null || $expectFalseFailed || !empty($validationErrors));

                // --- Lógica de Prompts y Obtención de Valores ---
                $promptOptions = [
                    'label' => $description,
                    'required' => in_array('required', $rules),
                ];

                if ($type === 'array_multiple') { // Múltiple selección, el comportamiento existente
                    if ($promptNeeded) {
                        $options = $this->getArrayOptionsFromRules($rules);
                        if (empty($options)) {
                            throw new \Exception("Para 'type: {$type}', se requiere una regla 'in:option1,option2' en las reglas del parámetro '{$key}'.");
                        }
                        $promptOptions['options'] = $options;
                        $promptOptions['default'] = (array)($initialParameters[$key] ?? []); // Multiselect espera array
                        $value = \Laravel\Prompts\multiselect(...$promptOptions);
                    }
                } elseif ($type === 'array_unique') { // Selección única de un array, devuelve un solo string
                    if ($promptNeeded) {
                        $options = $this->getArrayOptionsFromRules($rules);
                        if (empty($options)) {
                            throw new \Exception("Para 'type: {$type}', se requiere una regla 'in:option1,option2' en las reglas del parámetro '{$key}'.");
                        }
                        $promptOptions['options'] = $options;
                        // Para select, el default debe ser un string o null
                        $promptOptions['default'] = (string)($initialParameters[$key] ?? null);
                        $value = \Laravel\Prompts\select(...$promptOptions);
                    }
                } elseif ($type === 'boolean') {
                    if ($promptNeeded) {
                        // Si el closure retorna un booleano, usarlo como valor por defecto
                        $defaultBool = false;
                        if (is_callable($closureForPromptOrValue)) {
                            $closureResult = $closureForPromptOrValue();
                            if (is_bool($closureResult)) {
                                $defaultBool = $closureResult;
                            }
                        }
                        $promptOptions['default'] = (bool)($initialParameters[$key] ?? $defaultBool);
                        $value = \Laravel\Prompts\confirm(...$promptOptions);
                    }
                } elseif ($type === 'string_select') {
                    // LÓGICA PARA STRING_SELECT
                    if (is_callable($closureForPromptOrValue)) {
                        $closureResult = $closureForPromptOrValue(); // Ejecutar el closure para obtener el valor o las opciones

                        // Manejo de señales de cancelación/navegación de FpFileBrowser
                        if (is_string($closureResult) && ($closureResult === FpFileBrowser::CANCEL_SIGNAL || $closureResult === FpFileBrowser::BACK_TO_ROOT_SELECTION_SIGNAL)) {
                            $value = null; // Esto forzará una revalidación o re-prompt si es 'required'
                            // No usar continue aquí, dejar que la validación estándar maneje el 'required'
                        } elseif (is_string($closureResult) && !empty($closureResult)) {
                            // El closure retornó directamente un string, se selecciona automáticamente
                            $value = $closureResult;
                            \Laravel\Prompts\info("Seleccionado automáticamente para '{$key}': " . $value);
                        } elseif (is_array($closureResult)) {
                            $filteredOptions = array_filter($closureResult, 'is_string'); // Asegurar que las opciones sean strings
                            if (count($filteredOptions) === 1) {
                                // Si solo hay una opción string válida, se selecciona automáticamente
                                $value = array_values($filteredOptions)[0];
                                \Laravel\Prompts\info("Seleccionado automáticamente para '{$key}' (única opción): " . $value);
                            } elseif (count($filteredOptions) > 1) {
                                // Múltiples opciones, mostrar un prompt de selección
                                if ($promptNeeded) { // Solo si se necesita un prompt
                                    $promptOptions['options'] = array_combine($filteredOptions, $filteredOptions); // Claves y valores iguales para select
                                    $promptOptions['default'] = (string)($initialParameters[$key] ?? $value ?? ''); // Usar el valor actual o el inicial
                                    $value = \Laravel\Prompts\select(...$promptOptions);
                                }
                            } else {
                                // El closure retornó un array vacío o sin strings válidos
                                \Laravel\Prompts\warning("El closure para '{$key}' no retornó opciones válidas para STRING_SELECT.");
                                $value = null; // Forzar a pedir de nuevo si es requerido
                            }
                        } else {
                            // El closure retornó un tipo de dato inesperado para STRING_SELECT
                            \Laravel\Prompts\warning("El closure para '{$key}' retornó un tipo de dato inesperado para STRING_SELECT.");
                            $value = null; // Forzar a pedir de nuevo si es requerido
                        }
                    } else {
                        \Laravel\Prompts\error("El tipo 'string_select' requiere un 'closure' que retorne el valor o un array de opciones.");
                        $value = null; // Forzar a pedir de nuevo o error si es requerido
                    }
                } elseif (!empty($this->getSelectOptionsFromRules($rules))) { // Select estándar basado en regla 'in'
                    if ($promptNeeded) {
                        $promptOptions['options'] = $this->getSelectOptionsFromRules($rules);
                        $promptOptions['default'] = (string)($initialParameters[$key] ?? '');
                        $value = \Laravel\Prompts\select(...$promptOptions);
                    }
                } else { // Entrada de texto estándar
                    if ($promptNeeded) {
                        // Si hay closure y no es array_multiple/unique ni string_select, el closure se usa para el default
                        $defaultText = (is_callable($closureForPromptOrValue) && $type === 'string')
                            ? $closureForPromptOrValue()
                            : null;

                        $promptOptions['placeholder'] = 'Ingresa el valor para ' . $key;
                        $promptOptions['default'] = (string)($initialParameters[$key] ?? $defaultText ?? '');
                        $value = \Laravel\Prompts\text(...$promptOptions);
                    }
                }

                // --- Manejo de la regla 'expect_false' ---
                $expectFalseClosure = $rules['expect_false'] ?? null;
                if (is_callable($expectFalseClosure)) {
                    // Solo si el valor no es nulo, para permitir que 'required' falle primero si aplica
                    if ($value !== null && $expectFalseClosure($value)) {
                        $isValid = false;
                        $expectFalseFailed = true;
                        \Laravel\Prompts\error('El valor ingresado ya existe o no cumple la condición de unicidad. Por favor, intenta con un valor diferente.');
                        \Laravel\Prompts\info("Valor actual para '$key': " . ($value ?? 'Vacío'));
                        $value = null; // Fuerza a pedir de nuevo
                        continue; // Volver al inicio del do-while
                    }
                }

                // --- Validación estándar con Laravel Validator para las demás reglas ---
                $validationRulesForLaravel = collect($rules)->except('expect_false')->toArray();

                // Si el valor es null y es requerido, y no es string_select que ya lo manejó
                if ($value === null && in_array('required', $rules) && $type !== 'string_select') {
                    $isValid = false;
                    // El validador de Laravel proporcionará el mensaje de error "The X field is required."
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
                            \Laravel\Prompts\error($error); // Mostrar errores de validación con Prompts
                        }
                        \Laravel\Prompts\info("Por favor, corrige el valor para '$key'.");
                        $value = null; // Asegurar que el valor se pida de nuevo
                    } else {
                        // Asegurar el tipo de dato final y aplicar unicidad si es 'array_unique'
                        switch ($type) {
                            case 'array_unique':
                                $value = (string)$value; // <<-- Ahora devuelve un string único
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
