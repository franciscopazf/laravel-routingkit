<?php

namespace Fp\FullRoute\Contracts;

interface RouteValidatorInterface
{
    public function validate(array $route): bool;

    public function getErrors(): array;
}
