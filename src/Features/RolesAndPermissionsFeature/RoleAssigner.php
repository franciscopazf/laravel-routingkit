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

    public function assign(array $users): self
    {
        foreach ($users as $data) {
            $user = User::where('email', $data['user']['email'])->first();
            if ($user) {
                $user->syncRoles($data['roles']);
            }
        }
        return $this;
    }

    /**
     * Crear roles a partir de un array
     * Cada rol puede tener for_tenant (opcional)
     */
    public function createRoles(array $roles): self
    {
        foreach ($roles as $roleData) {
            $roleName = $roleData['id'] ?? $roleData['name'];
            $roleLabel = $roleData['name'] ?? $roleData['id'];
            $forTenant = $roleData['for_tenant'] ?? false;

            Role::firstOrCreate(
                ['name' => $roleName],
                ['label' => $roleLabel, 'for_tenant' => $forTenant]
            );
        }
        return $this;
    }

    public function deletePermissionsOfRoles(array $roles): self
    {
        foreach ($roles as $roleName => $label) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $permissionIds = $role->permissions->pluck('id');
                $role->syncPermissions([]);
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
