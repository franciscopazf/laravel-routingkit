<?php

namespace FPJ\RoutingKit\Features\DataRepositoryFeature;

use FPJ\RoutingKit\Contracts\FPJDataRepositoryInterface;
use FPJ\RoutingKit\Features\DataRepositoryFeature\FPJObjectDataRepository;
use FPJ\RoutingKit\Enums\FPJFileSupportEnum;

class FPJDataRepositoryFactory
{
    public static function getRepository(string $filePath, string $fileSave, bool $onlyStringSupport = false): FPJDataRepositoryInterface
    {
        switch ($fileSave) {
            case FPJFileSupportEnum::OBJECT_FILE_TREE || FPJFileSupportEnum::OBJECT_FILE_PLAIN:
                return new FPJObjectDataRepository($filePath, $fileSave, $onlyStringSupport);
            default:
                throw new \InvalidArgumentException("Unsupported transformer type: {$fileSave}");
        }
    }

    // otro metodo para identificar el contenido del archivo si es 
    // array plano, si es un arreglo de objetos o si es un json 
    // una ves identificado devolver la estrategia adecuada.
}
