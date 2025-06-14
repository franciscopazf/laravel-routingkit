<?php
namespace Fp\RoutingKit\Features\DataFileTransformersFeature;

use Fp\RoutingKit\Contracts\FpFileTransformerInterface;
use Fp\RoutingKit\Enums\FileSupportEnum;
use Fp\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer\FpObjectTreeTransformer;
use Fp\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer\FpObjectPlainTransformer;

class FpFileTransformerFactory
{
    public static function getFileTransformer(string $fileString, string $fileSave, bool $onlyStringSupport = false): FpFileTransformerInterface
    {
        switch ($fileSave) {
            case FileSupportEnum::OBJECT_FILE_TREE:
                return new FpObjectTreeTransformer($fileString, $onlyStringSupport);

            case FileSupportEnum::OBJECT_FILE_PLAIN:
                return new FpObjectPlainTransformer($fileString, $onlyStringSupport);

            default:
                throw new \InvalidArgumentException("Unsupported transformer type: {$fileSave}");
        }
    }
}
