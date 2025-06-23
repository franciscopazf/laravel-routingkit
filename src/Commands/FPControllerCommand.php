<?php

namespace FP\RoutingKit\Commands;

use FP\RoutingKit\Features\FileCreatorFeature\FPFileCreator;
use FP\RoutingKit\Features\InteractiveFeature\FPCreateSimpleController;
use Illuminate\Console\Command;

class FPControllerCommand extends Command
{
  // variables necesarias (opcionales)
  protected $signature = 'fp:controller';
  protected $description = 'Comando para gestionar rutas FPRoutingKit';

  public function handle()
  {
    FPCreateSimpleController::make()
      ->run();
  }
}
