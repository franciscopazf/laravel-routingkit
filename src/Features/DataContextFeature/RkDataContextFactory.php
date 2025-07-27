<?php

namespace Rk\RoutingKit\Features\DataContextFeature;


use Rk\RoutingKit\Enums\RkFileSupportEnum;
use Rk\RoutingKit\Contracts\RkDataRepositoryInterface;
use Rk\RoutingKit\Contracts\RkContextEntitiesInterface;
use Rk\RoutingKit\Features\DataRepositoryFeature\RkDataRepositoryFactory;
use Rk\RoutingKit\Features\DataContextFeature\RkileDataContext;

class RkDataContextFactory
{
    public static function getDataContext(
        string $contextId,
        string $filePath,
        string $fileSave,
        bool $onlyStringSupport = false
    ): RkContextEntitiesInterface {

        switch ($fileSave) {
            case RkFileSupportEnum::OBJECT_FILE_TREE || RkFileSupportEnum::OBJECT_FILE_PLAIN:
                return RkileDataContext::make($contextId, RkDataRepositoryFactory::getRepository(
                    $filePath,
                    $fileSave,
                    $onlyStringSupport
                ));
            default:
                throw new \InvalidArgumentException("Unsupported transformer type: {$fileSave}");
        }

        return static::getDataContextFromRepository($contextId, $rkDataRepository);
    }

    public static function getDataContextFromRepository(
        string $contextId,
        RkDataRepositoryInterface $rkDataRepository
    ): RkContextEntitiesInterface {
        return RkileDataContext::make($contextId, $rkDataRepository);
    }
}
