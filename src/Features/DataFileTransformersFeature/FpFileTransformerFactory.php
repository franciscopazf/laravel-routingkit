<?php
namespace FpF\RoutingKit\Features\DataFileTransformersFeature;

use FpF\RoutingKit\Contracts\FpFileTransformerInterface;
use FpF\RoutingKit\Enums\FileSupportEnum;
use FpF\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer\FpFObjectTreeTransformer;
use FpF\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer\FpFObjectPlainTransformer;

class FpFileTransformerFactory
{
    public static function getFileTransformer(string $fileString, string $fileSave, bool $onlyStringSupport = false): FpFileTransformerInterface
    {
        switch ($fileSave) {
            case FileSupportEnum::OBJECT_FILE_TREE:
                return new FpFObjectTreeTransformer($fileString, $onlyStringSupport);

            case FileSupportEnum::OBJECT_FILE_PLAIN:
                return new FpFObjectPlainTransformer($fileString, $onlyStringSupport);

            default:
                throw new \InvalidArgumentException("Unsupported transformer type: {$fileSave}");
        }
    }
}
