<?php

namespace Fp\RoutingKit\Features\RolesAndPermissionsFeature;

use Spatie\Permission\Models\Role;

class RoleCreator
{
    public static function make(): self
    {
        return new self();
    }

    /**
     * Create roles based on the provided array.
     *
     * @param array $roles An associative array where keys are role names and values are labels.
     */

    public function create(array $roles): void
    {
        foreach ($roles as $role => $label)
            Role::firstOrCreate(['name' => $role]);
    }


    

}
