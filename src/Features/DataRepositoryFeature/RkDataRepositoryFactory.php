<?php

namespace Rk\RoutingKit\Features\DataRepositoryFeature;

use Rk\RoutingKit\Contracts\RkDataRepositoryInterface;
use Rk\RoutingKit\Features\DataRepositoryFeature\RkObjectDataRepository;
use Rk\RoutingKit\Enums\RkFileSupportEnum;

class RkDataRepositoryFactory
{
    public static function getRepository(string $filePath, string $fileSave, bool $onlyStringSupport = false): RkDataRepositoryInterface
    {
        switch ($fileSave) {
            case RkFileSupportEnum::OBJECT_FILE_TREE || RkFileSupportEnum::OBJECT_FILE_PLAIN:
                return new RkObjectDataRepository($filePath, $fileSave, $onlyStringSupport);
            default:
                throw new \InvalidArgumentException("Unsupported transformer type: {$fileSave}");
        }
    }

    // otro metodo para identificar el contenido del archivo si es 
    // array plano, si es un arreglo de objetos o si es un json 
    // una ves identificado devolver la estrategia adecuada.
}
