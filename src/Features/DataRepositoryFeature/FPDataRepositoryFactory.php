<?php

namespace FP\RoutingKit\Features\DataRepositoryFeature;

use FP\RoutingKit\Contracts\FPDataRepositoryInterface;
use FP\RoutingKit\Features\DataRepositoryFeature\FPObjectDataRepository;
use FP\RoutingKit\Enums\FPFileSupportEnum;

class FPDataRepositoryFactory
{
    public static function getRepository(string $filePath, string $fileSave, bool $onlyStringSupport = false): FPDataRepositoryInterface
    {
        switch ($fileSave) {
            case FPFileSupportEnum::OBJECT_FILE_TREE || FPFileSupportEnum::OBJECT_FILE_PLAIN:
                return new FPObjectDataRepository($filePath, $fileSave, $onlyStringSupport);
            default:
                throw new \InvalidArgumentException("Unsupported transformer type: {$fileSave}");
        }
    }

    // otro metodo para identificar el contenido del archivo si es 
    // array plano, si es un arreglo de objetos o si es un json 
    // una ves identificado devolver la estrategia adecuada.
}
