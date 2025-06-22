<?php

namespace FpF\RoutingKit\Features\DataRepositoryFeature;

use FpF\RoutingKit\Contracts\FpFDataRepositoryInterface;
use FpF\RoutingKit\Features\DataRepositoryFeature\FpFBaseFileDataRepository;
use Illuminate\Support\Collection;

class FpFObjectDataRepository extends FpFBaseFileDataRepository implements FpFDataRepositoryInterface
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