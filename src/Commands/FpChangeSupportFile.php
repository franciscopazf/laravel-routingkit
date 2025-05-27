<?php

namespace Fp\FullRoute\Commands;

use Fp\FullRoute\Services\Route\FullRouteInteractive;
use Illuminate\Console\Command;
use function Laravel\Prompts\select;

use Fp\FullRoute\Helpers\Navigator;
use Fp\FullRoute\Clases\FullRoute;
use Fp\FullRoute\Services\Navigator\Navigator as NNavigator;



class FpChangeSupportFile extends Command
{
    // variables necesarias (opcionales)
    protected $signature = 'fp:change-support-file';

    protected $description = 'Comando para cambiar el archivo de soporte de FullRoute';

    protected FullRouteInteractive $interactive;

    public function handle()
    {
        dd('Este comando ha sido eliminado. Por favor, utiliza el comando "fp:route" para gestionar las rutas de FullRoute.');
    }

}
