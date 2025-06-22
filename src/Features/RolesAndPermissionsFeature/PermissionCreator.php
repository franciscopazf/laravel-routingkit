<?php


namespace FPJ\RoutingKit\Features\RolesAndPermissionsFeature;

use FPJ\RoutingKit\Contracts\FPJEntityInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionCreator
{

    // crear a los usuarios en caso de que no existan 
    // crear todos los roles 
    // crear todos los permisos


    // asginar los roles a los usuarios
    // asginar los permisos a los roles

    public static function make(): self
    {
        return new self();
    }

    /**
     * @param class-string<FPJEntityInterface> $fpjEntityClass
     */
    public function rebuildAll(string $fpjEntityClass): void
    {
        if (!is_subclass_of($fpjEntityClass, FPJEntityInterface::class))
            throw new \InvalidArgumentException("Class must implement FPJEntityInterface");

        $routes = $fpjEntityClass::allFlattened();

        // dd($routes);

        foreach ($routes as $route) {
            #echo "Processing route: {$route->getId()}\n";
            // a. Crear permisos
            foreach ($route->getAllPermissions() as $permissionName) {
                #echo "Creating permission: {$permissionName}\n";
                Permission::firstOrCreate(['name' => $permissionName]);
            }
            // dd($route->getRoles());
            // b. Asignar permisos a roles
            $roleMap = $route->getRoles(); // ['admin' => ['perm1', 'perm2'], 'user']

            foreach ($roleMap as $roleName => $permList) {
                if (is_array($permList)) {
                    #echo "Processing role: {$roleName} with permissions: " . implode(', ', $permList) . "\n";
                    // formato: 'admin' => ['perm1', 'perm2']
                    $role = Role::where('name', $roleName)
                        ->first();
                    $permissions = Permission::whereIn('name', $permList)
                        ->get();
                    // agregar a permissions el rol de acceso a la ruta o el permission individual
                    $permissions->push(Permission::firstOrCreate(['name' => $route->getAccessPermission()]));

                    ///dd($permissions);
                } else {
                    #echo "||| Processing role: {$permList} with permissions: " . implode(', ', $route->getAccessPermissions()) . "\n";
                    // formato: 'user' (sin array)
                    $role = Role::where('name', $permList)
                        ->first();
                    $permissions = Permission::whereIn('name', $route->getAllPermissions())
                        ->get();
                }

                if (!$role) {
                    #echo "Role {$roleName} does not exist, creating it.\n";
                    $role = Role::firstOrCreate(['name' => $roleName]);
                }
                #echo "Assigning permissions to role: {$role->name}\n";
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
