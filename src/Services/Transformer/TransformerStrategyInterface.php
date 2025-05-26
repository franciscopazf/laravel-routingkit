<?php

namespace Fp\FullRoute\Services\Transformer;

use Fp\FullRoute\Clases\FullRoute;
use Illuminate\Support\Collection;

interface TransformerStrategyInterface
{
    public function transform(Collection $fRoutes): string;

    public function getHeaderBlock(): string;

    public function getContentBlock(Collection $routes): string;

    public function getFooterBlock(): string;

}
