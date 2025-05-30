<?php

namespace Fp\FullRoute\Entities;

use Fp\FullRoute\Clases\FullRoute;
use Fp\FullRoute\Traits\HasDynamicAccessors;
class FPNavigation
{
    use HasDynamicAccessors;
    public FullRoute $route;
    
    public string $id;

    public ?string $parentId = null;

    public function __construct() {}

    public static function make(string $id, ?string $routeId = null): self
    {
        $instance = new self();
        $instance->id = $id;
        $instance->route = FullRoute::find($routeId);
        if (!$instance->route) {
            throw new \Exception("Route with ID {$routeId} not found.");
        }
        return $instance;
    }
}
