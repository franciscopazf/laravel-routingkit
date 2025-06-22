<?php

namespace FPJ\RoutingKit\Commands;

use FPJ\RoutingKit\Features\FileCreatorFeature\FPJFileCreator;
use FPJ\RoutingKit\Features\InteractiveFeature\FPJCreateSimpleController;
use Illuminate\Console\Command;

class FPJControllerCommand extends Command
{
  // variables necesarias (opcionales)
  protected $signature = 'fpj:controller';
  protected $description = 'Comando para gestionar rutas FPJRoutingKit';

  public function handle()
  {
    FPJCreateSimpleController::make()
      ->run();
  }
}
