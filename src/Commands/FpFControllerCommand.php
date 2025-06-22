<?php

namespace FpF\RoutingKit\Commands;

use FpF\RoutingKit\Features\FileCreatorFeature\FpFFileCreator;
use FpF\RoutingKit\Features\InteractiveFeature\FpFCreateSimpleController;
use Illuminate\Console\Command;

class FpFControllerCommand extends Command
{
  // variables necesarias (opcionales)
  protected $signature = 'fpf:controller';
  protected $description = 'Comando para gestionar rutas FpFRoutingKit';

  public function handle()
  {
    FpFCreateSimpleController::make()
      ->run();
  }
}
