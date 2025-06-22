<?php

namespace FPJ\RoutingKit\Features\DataRepositoryFeature;

use FPJ\RoutingKit\Contracts\FPJDataRepositoryInterface;
use FPJ\RoutingKit\Features\DataRepositoryFeature\FPJBaseFileDataRepository;
use Illuminate\Support\Collection;

class FPJObjectDataRepository extends FPJBaseFileDataRepository implements FPJDataRepositoryInterface
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