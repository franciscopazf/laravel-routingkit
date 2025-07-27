<?php

namespace Rk\RoutingKit\Commands;

use Rk\RoutingKit\Features\FileCreatorFeature\RkFileCreator;
use Rk\RoutingKit\Features\InteractiveFeature\RkCreateSimpleController;
use Illuminate\Console\Command;

class RkControllerCommand extends Command
{
  // variables necesarias (opcionales)
  protected $signature = 'rk:controller';
  protected $description = 'Comando para gestionar rutas RkRoutingKit';

  public function handle()
  {
    RkCreateSimpleController::make()
      ->run();
  }
}
