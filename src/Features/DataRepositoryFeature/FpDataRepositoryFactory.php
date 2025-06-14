<?php

namespace Fp\RoutingKit\Features\DataRepositoryFeature;

use Fp\RoutingKit\Contracts\FpDataRepositoryInterface;
use Fp\RoutingKit\Features\DataRepositoryFeature\FpObjectDataRepository;
use Fp\RoutingKit\Enums\FileSupportEnum;

class FpDataRepositoryFactory
{
    public static function getRepository(string $filePath, string $fileSave, bool $onlyStringSupport = false): FpDataRepositoryInterface
    {
        switch ($fileSave) {
            case FileSupportEnum::OBJECT_FILE_TREE || FileSupportEnum::OBJECT_FILE_PLAIN:
                return new FpObjectDataRepository($filePath, $fileSave, $onlyStringSupport);
            default:
                throw new \InvalidArgumentException("Unsupported transformer type: {$fileSave}");
        }
    }

    // otro metodo para identificar el contenido del archivo si es 
    // array plano, si es un arreglo de objetos o si es un json 
    // una ves identificado devolver la estrategia adecuada.
}
