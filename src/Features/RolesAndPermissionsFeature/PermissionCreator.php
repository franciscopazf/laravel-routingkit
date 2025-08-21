<?php

namespace Rk\RoutingKit\Features\RolesAndPermissionsFeature;

use Rk\RoutingKit\Contracts\RkEntityInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionCreator
{
    public static function make(): self
    {
        return new self();
    }

    /**
     * @param class-string<RkEntityInterface> $rkEntityClass
     */
    public function rebuildAll(string $rkEntityClass): void
    {
        if (!is_subclass_of($rkEntityClass, RkEntityInterface::class))
            throw new \InvalidArgumentException("Class must implement RkEntityInterface");

        $routes = $rkEntityClass::allFlattened();

        foreach ($routes as $route) {
            // Crear permisos
            foreach ($route->getAllPermissions() as $permissionName => $options) {
                $forTenant = $options['for_tenant'] ?? false;

                // Si el permiso es for_tenant, solo crearlo si existe al menos un rol for_tenant
                if ($forTenant) {
                    $hasTenantRole = false;
                    foreach ($route->getRoles() as $roleName => $perms) {
                        $role = Role::where('name', $roleName)->first();
                        if ($role && ($role->for_tenant ?? false)) {
                            $hasTenantRole = true;
                            break;
                        }
                    }
                    if (!$hasTenantRole) {
                        continue; // saltar este permiso
                    }
                }

                Permission::firstOrCreate(['name' => $permissionName], ['for_tenant' => $forTenant]);
            }

            // Asignar permisos a roles
            $roleMap = $route->getRoles();

            foreach ($roleMap as $roleName => $permList) {
                if (is_array($permList)) {
                    $role = Role::firstOrCreate(['name' => $roleName]);
                    $permissions = Permission::whereIn('name', $permList)->get();
                    $permissions->push(Permission::firstOrCreate(['name' => $route->getAccessPermission()]));
                } else {
                    $role = Role::firstOrCreate(['name' => $permList]);
                    $permissions = Permission::whereIn('name', $route->getAllPermissions())->get();
                }

                $role->givePermissionTo($permissions);
            }
        }

        $this->printRolesWithPermissions();
    }

    public function printRolesWithPermissions(): void
    {
        $roles = Role::with('permissions')->get();
        foreach ($roles as $role) {
            echo "Role: {$role->name}\n";
            foreach ($role->permissions as $permission) {
                echo "- Permission: {$permission->name}\n";
            }
        }
    }
}
