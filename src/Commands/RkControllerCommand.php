<?php

namespace Rk\RoutingKit\Commands;

use Rk\RoutingKit\Features\FileCreatorFeature\RkFileCreator;
use Rk\RoutingKit\Features\InteractiveFeature\RkCreateSimpleController;
use Illuminate\Console\Command;
use Rk\RoutingKit\Features\InteractiveFeature\RkContextCreateController;
use Rk\RoutingKit\Features\InteractiveFeature\RkCreateGlobalController;

class RkControllerCommand extends Command
{
  // variables necesarias (opcionales)
  protected $signature = 'rk:controller';
  protected $description = 'Comando para gestionar rutas RkRoutingKit';

  public function handle()
  {
    RkContextCreateController::make(
       RkCreateGlobalController::make()
    )->run();
   

    // RkCreateSimpleController::make()
    //   ->run();
  }
}
