<?php

namespace Fp\FullRoute\Services\Route;

use function Laravel\Prompts\Text;
use function Laravel\Prompts\Select;
use function Laravel\Prompts\Multiselect;
use function Laravel\Prompts\Confirm;

use Fp\FullRoute\Entities\FpRoute as FullRoute;
use Fp\FullRoute\Services\Navigator\Navigator;

class FullRouteInteractive
{

    public function __construct()
    {
        // Constructor vacío
    }

    public function crear(array $datos = [])
    {
        // Validar ID único
        do {
            $id = $datos['id'] ?? text('🆔 ID de la ruta');
            if (FullRoute::exists($id)) {
                $this->error("❌ El ID '{$id}' ya existe. Por favor, elige otro.");
                unset($datos['id']);
            }
        } while (FullRoute::exists($id));

        // si $datos['controller'] es null entonces se debe obtener de la ruta actual
        if (!isset($datos['controller']) ) {
        
            $dataControlador = Navigator::make()
                ->getControllerRouteParams();
            $datos['controller'] =   $dataControlador->controller;
            $datos['action'] =  $dataControlador->action;
            
        }

      //  convertir el id a minúsculas
        $idMinusculas = strtolower($id);

        // Crear ruta
        $ruta = FullRoute::make($id)
            ->setAccessPermission($datos['permission'] ?? 'acceder-' . $idMinusculas)
            ->setUrlMethod($datos['method'] ?? select('📥 Método HTTP', ['GET', 'POST', 'PUT', 'DELETE']))
            ->setUrlController($datos['controller'] ?? text('🏗️ Controlador de la ruta'))
            ->setUrlAction($datos['action'] ?? text('⚙️ Acción del controlador'))
            ->setRoles($datos['roles'] ?? multiselect('👥 Roles permitidos', config('fproute.roles')))
            ->setEndBlock($id);

        $parent = $datos['parent'] ?? FullRoute::seleccionar(label: '📁 Selecciona la ruta padre');
        $this->confirmar("⚠️ ¿Estás seguro de que deseas crear la ruta con ID '{$id}'?");
        $ruta->save(parent: $parent);
        $this->info("✅ Ruta con ID '{$id}' creada correctamente.");
    }


    public function eliminar(?string $id = null)
    {
        $id = $id ?? FullRoute::seleccionar(label: '🗑️ Selecciona la ruta a eliminar');
        $ruta = FullRoute::findById($id);
      
        if (!$ruta) {
            return $this->error("❌ No se encontró la ruta con ID '{$id}'.");
        }

        $this->confirmar("⚠️ ¿Estás seguro de que deseas eliminar la ruta con ID '{$id}'? Esta acción no se puede deshacer.");
        $ruta->delete();
    }

    public function reescribir()
    {
        $this->confirmar("🔄 ¿Estás seguro de que deseas reescribir las rutas? Esto actualizará todas las rutas existentes.");
        FullRoute::rewriteAllContext();
        $this->info("✅ Rutas reescritas correctamente.");
    }

    protected function confirmar(
        string $mensaje,
        string $messageYes = 'Opción Aceptada',
        string $messageNo = 'Opción Cancelada',
    ): mixed {
        $confirmacion = confirm($mensaje, default: false);
        if (!$confirmacion) {
            $this->error($messageNo);
            die();
        }
        $this->info($messageYes);
        return $confirmacion;
    }

    // Métodos auxiliares de salida
    protected function info(string $mensaje): void
    {
        echo "\e[32m{$mensaje}\e[0m\n"; // Verde
    }

    protected function error(string $mensaje): void
    {
        echo "\e[31m{$mensaje}\e[0m\n"; // Rojo
    }
}
