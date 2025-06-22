<?php
namespace FPJ\RoutingKit\Features\DataFileTransformersFeature;

use FPJ\RoutingKit\Contracts\FPJileTransformerInterface;
use FPJ\RoutingKit\Enums\FPJFileSupportEnum;
use FPJ\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer\FPJObjectTreeTransformer;
use FPJ\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer\FPJObjectPlainTransformer;

class FPJileTransformerFactory
{
    public static function getFileTransformer(string $fileString, string $fileSave, bool $onlyStringSupport = false): FPJileTransformerInterface
    {
        switch ($fileSave) {
            case FPJFileSupportEnum::OBJECT_FILE_TREE:
                return new FPJObjectTreeTransformer($fileString, $onlyStringSupport);

            case FPJFileSupportEnum::OBJECT_FILE_PLAIN:
                return new FPJObjectPlainTransformer($fileString, $onlyStringSupport);

            default:
                throw new \InvalidArgumentException("Unsupported transformer type: {$fileSave}");
        }
    }
}
