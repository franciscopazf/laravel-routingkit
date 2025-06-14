<?php

namespace Fp\RoutingKit\Features\DataContextFeature;


use Fp\RoutingKit\Enums\FileSupportEnum;
use Fp\RoutingKit\Contracts\FpDataRepositoryInterface;
use Fp\RoutingKit\Contracts\FpContextEntitiesInterface;
use Fp\RoutingKit\Features\DataRepositoryFeature\FpDataRepositoryFactory;
use Fp\RoutingKit\Features\DataContextFeature\FpFileDataContext;

class FpDataContextFactory
{
    public static function getDataContext(
        string $contextId,
        string $filePath,
        string $fileSave,
        bool $onlyStringSupport = false
    ): FpContextEntitiesInterface {

        switch ($fileSave) {
            case FileSupportEnum::OBJECT_FILE_TREE || FileSupportEnum::OBJECT_FILE_PLAIN:
                return FpFileDataContext::make($contextId, FpDataRepositoryFactory::getRepository(
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
        FpDataRepositoryInterface $fpDataRepository
    ): FpContextEntitiesInterface {
        return FpFileDataContext::make($contextId, $fpDataRepository);
    }
}
