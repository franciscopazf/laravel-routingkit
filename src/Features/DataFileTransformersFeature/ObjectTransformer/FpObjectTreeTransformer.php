<?php

namespace Fp\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer;

use Fp\RoutingKit\Contracts\FpFileTransformerInterface;

class FpObjectTreeTransformer extends FpBaseObjectTransformer implements FpFileTransformerInterface
{
    public function __construct(string $fileString, bool $onlyStringSupport = false)
    {
        parent::__construct($fileString, $onlyStringSupport);
    }

}