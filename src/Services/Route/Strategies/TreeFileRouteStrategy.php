<?php

namespace Fp\FullRoute\Services\Route\Strategies;

use Fp\FullRoute\Clases\FullRoute;
use Illuminate\Support\Collection;

class TreeFileRouteStrategy extends BaseRouteStrategy
{

    public function getTransformerType(): string
    {
        return 'file_tree';
    }
}
