<?php

namespace Fp\RoutingKit\Services\Transformer;

use Fp\RoutingKit\Clases\RoutingKit;
use Illuminate\Support\Collection;

interface TransformerStrategyInterface
{
    public function transform(Collection $fRoutes): string;

    public function getHeaderBlock(): string;

    public function getContentBlock(Collection $routes): string;

    public function getFooterBlock(): string;

}
