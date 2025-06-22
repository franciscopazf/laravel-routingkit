<?php

namespace FpF\RoutingKit\Features\DataContextFeature;


use FpF\RoutingKit\Enums\FileSupportEnum;
use FpF\RoutingKit\Contracts\FpFDataRepositoryInterface;
use FpF\RoutingKit\Contracts\FpFContextEntitiesInterface;
use FpF\RoutingKit\Features\DataRepositoryFeature\FpFDataRepositoryFactory;
use FpF\RoutingKit\Features\DataContextFeature\FpFileDataContext;

class FpFDataContextFactory
{
    public static function getDataContext(
        string $contextId,
        string $filePath,
        string $fileSave,
        bool $onlyStringSupport = false
    ): FpFContextEntitiesInterface {

        switch ($fileSave) {
            case FileSupportEnum::OBJECT_FILE_TREE || FileSupportEnum::OBJECT_FILE_PLAIN:
                return FpFileDataContext::make($contextId, FpFDataRepositoryFactory::getRepository(
                    $filePath,
                    $fileSave,
                    $onlyStringSupport
                ));
            default:
                throw new \InvalidArgumentException("Unsupported transformer type: {$fileSave}");
        }

        return static::getDataContextFromRepository($contextId, $fpfDataRepository);
    }

    public static function getDataContextFromRepository(
        string $contextId,
        FpFDataRepositoryInterface $fpfDataRepository
    ): FpFContextEntitiesInterface {
        return FpFileDataContext::make($contextId, $fpfDataRepository);
    }
}
