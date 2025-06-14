<?php

namespace Fp\RoutingKit\Features\FileTransformersFeature\ObjectTransformer;

use Fp\RoutingKit\Contracts\FpFileTransformerInterface;


class FpObjectPlainTransformer extends FpBaseObjectTransformer implements FpFileTransformerInterface
{
    /**
     * @param mixed $object
     * @return string
     */
    public function transform($object): string
    {
        if (is_array($object)) {
            return json_encode($object, JSON_PRETTY_PRINT);
        }

        if (is_object($object)) {
            return json_encode($object, JSON_PRETTY_PRINT);
        }

        return (string)$object;
    }
}
