<?php

namespace Rk\RoutingKit\Features\DataRepositoryFeature;

use Rk\RoutingKit\Contracts\RkDataRepositoryInterface;
use Rk\RoutingKit\Features\DataRepositoryFeature\RkBaseFileDataRepository;
use Illuminate\Support\Collection;

class RkObjectDataRepository extends RkBaseFileDataRepository implements RkDataRepositoryInterface
{
    public function getData(): Collection
    {
        $data = $this->getContents();
        if (!is_array($data)) {
            throw new \RuntimeException("Data must be an array, got " . gettype($data));
        }
        return collect($data);
    }
}