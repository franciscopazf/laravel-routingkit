<?php

namespace FP\RoutingKit\Features\DataRepositoryFeature;

use FP\RoutingKit\Contracts\FPDataRepositoryInterface;
use FP\RoutingKit\Features\DataRepositoryFeature\FPBaseFileDataRepository;
use Illuminate\Support\Collection;

class FPObjectDataRepository extends FPBaseFileDataRepository implements FPDataRepositoryInterface
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