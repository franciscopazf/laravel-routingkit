<?php


namespace Rk\RoutingKit\Features\RolesAndPermissionsFeature;

use App\Models\User;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;


class RoleAssigner
{
    public static function make(): self
    {
        return new self();
    }

    /**
     * Assign roles to users based on the provided array.
     *
     * @param array $users An associative array where each element contains 'email' and 'roles'.
     */
    public function assign(array $users): self
    {
        foreach ($users as $data) {
            $user = User::where('email', $data['user']['email'])->first();

            if (!$user) {
                continue; // si no existe el usuario, saltar
            }

            // Filtrar solo los roles que existen en la BD
            $existingRoles = collect($data['roles'])->filter(function ($roleName) {
                return \Spatie\Permission\Models\Role::where('name', $roleName)->exists();
            });

            if ($existingRoles->isNotEmpty()) {
                $user->syncRoles($existingRoles->toArray());
            }
        }

        return $this;
    }

    /**
     * Create roles based on the provided array.
     *
     * @param array $roles An associative array where keys are role names and values are labels.
     */

    public function deletePermissionsOfRoles(array $roles): self
    {
        foreach ($roles as $roleName => $label) {
            $role = Role::where('name', $roleName)->first();

            if ($role) {
                // Obtener los IDs de los permisos de este rol
                $permissionIds = $role->permissions->pluck('id');

                // Desvincular permisos del rol
                $role->syncPermissions([]);

                // Eliminar los permisos de la base de datos si ya no están asignados a ningún otro rol
                foreach ($permissionIds as $permissionId) {
                    $permission = Permission::find($permissionId);

                    if ($permission && $permission->roles->isEmpty()) {
                        $permission->delete();
                    }
                }
            }
        }

        return $this;
    }
}
