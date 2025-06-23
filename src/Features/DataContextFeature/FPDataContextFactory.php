<?php

namespace FP\RoutingKit\Features\DataContextFeature;


use FP\RoutingKit\Enums\FPFileSupportEnum;
use FP\RoutingKit\Contracts\FPDataRepositoryInterface;
use FP\RoutingKit\Contracts\FPContextEntitiesInterface;
use FP\RoutingKit\Features\DataRepositoryFeature\FPDataRepositoryFactory;
use FP\RoutingKit\Features\DataContextFeature\FPileDataContext;

class FPDataContextFactory
{
    public static function getDataContext(
        string $contextId,
        string $filePath,
        string $fileSave,
        bool $onlyStringSupport = false
    ): FPContextEntitiesInterface {

        switch ($fileSave) {
            case FPFileSupportEnum::OBJECT_FILE_TREE || FPFileSupportEnum::OBJECT_FILE_PLAIN:
                return FPileDataContext::make($contextId, FPDataRepositoryFactory::getRepository(
                    $filePath,
                    $fileSave,
                    $onlyStringSupport
                ));
            default:
                throw new \InvalidArgumentException("Unsupported transformer type: {$fileSave}");
        }

        return static::getDataContextFromRepository($contextId, $fpDataRepository);
    }

    public static function getDataContextFromRepository(
        string $contextId,
        FPDataRepositoryInterface $fpDataRepository
    ): FPContextEntitiesInterface {
        return FPileDataContext::make($contextId, $fpDataRepository);
    }
}
