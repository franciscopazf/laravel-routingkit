<?php

namespace FpF\RoutingKit\Features\DataRepositoryFeature;

use FpF\RoutingKit\Contracts\FpFDataRepositoryInterface;
use FpF\RoutingKit\Features\DataRepositoryFeature\FpFObjectDataRepository;
use FpF\RoutingKit\Enums\FpFFileSupportEnum;

class FpFDataRepositoryFactory
{
    public static function getRepository(string $filePath, string $fileSave, bool $onlyStringSupport = false): FpFDataRepositoryInterface
    {
        switch ($fileSave) {
            case FpFFileSupportEnum::OBJECT_FILE_TREE || FpFFileSupportEnum::OBJECT_FILE_PLAIN:
                return new FpFObjectDataRepository($filePath, $fileSave, $onlyStringSupport);
            default:
                throw new \InvalidArgumentException("Unsupported transformer type: {$fileSave}");
        }
    }

    // otro metodo para identificar el contenido del archivo si es 
    // array plano, si es un arreglo de objetos o si es un json 
    // una ves identificado devolver la estrategia adecuada.
}
