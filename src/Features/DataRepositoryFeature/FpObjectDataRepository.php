<?php

namespace Fp\RoutingKit\Features\DataRepositoryFeature;

use Fp\RoutingKit\Contracts\FpDataRepositoryInterface;
use Fp\RoutingKit\Features\DataRepositoryFeature\FpBaseFileDataRepository;
use Illuminate\Support\Collection;

class FpObjectDataRepository extends FpBaseFileDataRepository implements FpDataRepositoryInterface
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