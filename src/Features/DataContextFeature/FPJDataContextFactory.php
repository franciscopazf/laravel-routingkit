<?php

namespace FPJ\RoutingKit\Features\DataContextFeature;


use FPJ\RoutingKit\Enums\FPJFileSupportEnum;
use FPJ\RoutingKit\Contracts\FPJDataRepositoryInterface;
use FPJ\RoutingKit\Contracts\FPJContextEntitiesInterface;
use FPJ\RoutingKit\Features\DataRepositoryFeature\FPJDataRepositoryFactory;
use FPJ\RoutingKit\Features\DataContextFeature\FPJileDataContext;

class FPJDataContextFactory
{
    public static function getDataContext(
        string $contextId,
        string $filePath,
        string $fileSave,
        bool $onlyStringSupport = false
    ): FPJContextEntitiesInterface {

        switch ($fileSave) {
            case FPJFileSupportEnum::OBJECT_FILE_TREE || FPJFileSupportEnum::OBJECT_FILE_PLAIN:
                return FPJileDataContext::make($contextId, FPJDataRepositoryFactory::getRepository(
                    $filePath,
                    $fileSave,
                    $onlyStringSupport
                ));
            default:
                throw new \InvalidArgumentException("Unsupported transformer type: {$fileSave}");
        }

        return static::getDataContextFromRepository($contextId, $fpjDataRepository);
    }

    public static function getDataContextFromRepository(
        string $contextId,
        FPJDataRepositoryInterface $fpjDataRepository
    ): FPJContextEntitiesInterface {
        return FPJileDataContext::make($contextId, $fpjDataRepository);
    }
}
