<?php

namespace Rk\RoutingKit\Features\InteractiveFeature;

use Rk\RoutingKit\Entities\RkNavigation;
use Rk\RoutingKit\Features\FileCreatorFeature\RkileCreator;
use Rk\RoutingKit\Entities\RkRoute;
use Illuminate\Support\Str;
use Rk\RoutingKit\Contracts\RkCreatorController;

use function Laravel\Prompts\info;
use function Laravel\Prompts\comment;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\error;
use function Laravel\Prompts\progress;


class RkContextCreateController
{
    protected RkCreatorController $creator;


    protected function __construct(RkCreatorController $creator = null)
    {
        $this->creator = $creator;
    }

    public static function make(?RkCreatorController $creator = null): self
    {
        return new self($creator);
    }

    public function run(?string $fullPath = null): void
    {
        if (!$this->creator) {
            error('No se ha proporcionado ningún creador.');
            return;
        }

        $this->creator->run($fullPath);
    }

    // metodos comunes para reemplazar.

}
