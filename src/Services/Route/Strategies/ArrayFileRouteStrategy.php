<?php

namespace Fp\RoutingKit\Services\Route\Strategies;

use Illuminate\Support\Collection;

class ArrayFileRouteStrategy extends BaseRouteStrategy
{

    public function getTransformerType(): string
    {
        return 'file_array';
    }

   
}
