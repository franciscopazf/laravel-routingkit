<?php

namespace Rk\RoutingKit\Features\InteractiveFeature;

use Rk\RoutingKit\Features\InteractiveFeature\RkileBrowser;

class RkModelResolver
{
    public static function make(): self
    {
        return new self();
    }

    public function run()
    {
        return RkileBrowser::make()->seleccionarClase(
            base_path('app/Models')
        );
    }
}
