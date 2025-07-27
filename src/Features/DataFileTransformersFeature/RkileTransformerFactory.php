<?php
namespace Rk\RoutingKit\Features\DataFileTransformersFeature;

use Rk\RoutingKit\Contracts\RkileTransformerInterface;
use Rk\RoutingKit\Enums\RkFileSupportEnum;
use Rk\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer\RkObjectTreeTransformer;
use Rk\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer\RkObjectPlainTransformer;

class RkileTransformerFactory
{
    public static function getFileTransformer(string $fileString, string $fileSave, bool $onlyStringSupport = false): RkileTransformerInterface
    {
        switch ($fileSave) {
            case RkFileSupportEnum::OBJECT_FILE_TREE:
                return new RkObjectTreeTransformer($fileString, $onlyStringSupport);

            case RkFileSupportEnum::OBJECT_FILE_PLAIN:
                return new RkObjectPlainTransformer($fileString, $onlyStringSupport);

            default:
                throw new \InvalidArgumentException("Unsupported transformer type: {$fileSave}");
        }
    }
}
