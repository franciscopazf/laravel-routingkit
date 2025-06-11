<?php

namespace Fp\FullRoute\Services\Route;

use function Laravel\Prompts\Text;
use function Laravel\Prompts\Select;
use function Laravel\Prompts\Multiselect;
use function Laravel\Prompts\Confirm;

use Fp\FullRoute\Entities\FpNavigation;
use Fp\FullRoute\Entities\FpRoute;
use Fp\FullRoute\Services\Navigator\Navigator;

class FpNavigationInteractive
{

    public function __construct()
    {
        // Constructor vacío
    }

    public static function make(): self
    {
        return new self();
    }

    public function crear(array $datos = [])
    {
        // Validar ID único
        if (empty($datos['id'])) {
            do {
                $id = FpRoute::seleccionar(label: " Selecciona la ruta para navegar ");
                if (FpNavigation::exists($id)) {
                    $this->error("❌ El ID '{$id}' ya existe. Por favor, elige otro.");
                    unset($datos['id']);
                }
            } while (FpNavigation::exists($id));
        } else {
            $id = $datos['id'];
        }


        //  convertir el id a minúsculas
        //        $idMinusculas = strtolower($id);

        // Crear ruta
        $ruta =  FpNavigation::make($id)
            ->setLabel($datos['label'] ?? text('🏷️ Label de la navegacion '))
            ->setIsFpRoute(true)
            ->setEndBlock($id);


        $parent = $datos['parent'] ?? FpNavigation::seleccionar(label: '📁 Selecciona la ruta padre');


        $this->confirmar("⚠️ ¿Estás seguro de que deseas crear la ruta con ID '{$id}'?");
        $ruta->save(parent: $parent);
        $this->info("✅ Ruta con ID '{$id}' creada correctamente.");
    }


    public function eliminar(?string $id = null)
    {
        $id = $id ?? FpNavigation::seleccionar(label: '🗑️ Selecciona la ruta a eliminar');
        $ruta = FpNavigation::findById($id);

        if (!$ruta) {
            return $this->error("❌ No se encontró la ruta con ID '{$id}'.");
        }

        $this->confirmar("⚠️ ¿Estás seguro de que deseas eliminar la ruta con ID '{$id}'? Esta acción no se puede deshacer.");
        $ruta->delete();
    }

    public function reescribir()
    {
        $this->confirmar("🔄 ¿Estás seguro de que deseas reescribir las rutas? Esto actualizará todas las rutas existentes.");
        FpNavigation::rewriteAllContext();
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
