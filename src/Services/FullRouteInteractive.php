<?php

namespace Fp\FullRoute\Services;

use function Laravel\Prompts\Text;
use function Laravel\Prompts\Select;
use function Laravel\Prompts\Multiselect;
use Fp\FullRoute\Clases\FullRoute;

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

        // Crear ruta
        $ruta = FullRoute::make($id)
            ->setPermission($datos['permission'] ?? text('🔐 Permiso de la ruta'))
            ->setTitle($datos['title'] ?? text('📌 Título de la ruta'))
            ->setDescription($datos['description'] ?? text('📝 Descripción de la ruta'))
            ->setKeywords($datos['keywords'] ?? text('🔑 Palabras clave (separadas por comas)'))
            ->setIcon($datos['icon'] ?? text('🎨 Icono de la ruta'))
            ->setUrl($datos['url'] ?? text('🔗 URL de la ruta'))
            ->setUrlName($datos['url_name'] ?? text('🧩 Nombre de la URL'))
            ->setUrlMethod($datos['method'] ?? select('📥 Método HTTP', ['GET', 'POST', 'PUT', 'DELETE']))
            ->setUrlController($datos['controller'] ?? text('🏗️ Controlador de la ruta'))
            ->setUrlAction($datos['action'] ?? text('⚙️ Acción del controlador'))
            ->setRoles($datos['roles'] ?? multiselect('👥 Roles permitidos', ['admin', 'user']))
            ->setChildrens([])
            ->setEndBlock($id);

        $parent = $datos['parent'] ?? FullRoute::seleccionar(label:'📁 Selecciona la ruta padre');
        $ruta->save(parent: $parent);

        $this->info("✅ Ruta con ID '{$id}' creada correctamente.");
    }

    public function mover(?string $idRuta = null, ?string $nuevoPadreId = null)
    {
        $idRuta = $idRuta ?? FullRoute::seleccionar(label: '📁 Selecciona la ruta a mover');
        $nuevoPadreId = $nuevoPadreId ?? FullRoute::seleccionar(omitId: $idRuta, label: '📁 Selecciona la nueva ruta padre');

        $ruta = FullRoute::find($idRuta);
        if (!$ruta) {
            return $this->error("❌ No se encontró la ruta con ID '{$idRuta}'.");
        }
        // validar primero que la ruta no sea padre de la nueva ruta
        if ($ruta->routeIsChild($nuevoPadreId)) {
            return $this->error("❌ No se puede mover la ruta '{$idRuta}' a sí misma o a una de sus rutas hijas.");
        }
        $ruta->moveTo($nuevoPadreId);
        $this->info("🔀 Ruta con ID '{$idRuta}' movida correctamente a '{$nuevoPadreId}'.");
    }

    public function eliminar(?string $id = null)
    {
        $id = $id ?? FullRoute::seleccionar(label:'🗑️ Selecciona la ruta a eliminar');
        $ruta = FullRoute::find($id);

        if (!$ruta) {
            return $this->error("❌ No se encontró la ruta con ID '{$id}'.");
        }

        $ruta->delete();
        $this->info("✅ Ruta con ID '{$id}' eliminada correctamente.");
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
