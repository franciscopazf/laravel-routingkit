<?php
namespace FP\RoutingKit\Features\DataFileTransformersFeature;

use FP\RoutingKit\Contracts\FPileTransformerInterface;
use FP\RoutingKit\Enums\FPFileSupportEnum;
use FP\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer\FPObjectTreeTransformer;
use FP\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer\FPObjectPlainTransformer;

class FPileTransformerFactory
{
    public static function getFileTransformer(string $fileString, string $fileSave, bool $onlyStringSupport = false): FPileTransformerInterface
    {
        switch ($fileSave) {
            case FPFileSupportEnum::OBJECT_FILE_TREE:
                return new FPObjectTreeTransformer($fileString, $onlyStringSupport);

            case FPFileSupportEnum::OBJECT_FILE_PLAIN:
                return new FPObjectPlainTransformer($fileString, $onlyStringSupport);

            default:
                throw new \InvalidArgumentException("Unsupported transformer type: {$fileSave}");
        }
    }
}
