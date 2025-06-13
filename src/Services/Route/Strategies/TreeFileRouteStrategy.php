<?php

namespace Fp\RoutingKit\Services\Route\Strategies;

use Fp\RoutingKit\Clases\RoutingKit;
use Illuminate\Support\Collection;

class TreeFileRouteStrategy extends BaseRouteStrategy
{

    public function getTransformerType(): string
    {
        return 'file_tree';
    }
}
