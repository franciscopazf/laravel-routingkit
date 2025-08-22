<?php

namespace Rk\RoutingKit\Features\RolesAndPermissionsFeature;

use Spatie\Permission\Models\Role;

class RoleCreator
{

    public function __construct(
        protected ?string $tenantId,
        protected ?bool $tenants = false
    ) {
    }

    public static function make(?string $tenantId, ?bool $tenants): self
    {
        return new self($tenantId, $tenants);
    }

    /**
     * Create roles based on the provided array.
     *
     * @param array $roles An associative array where keys are role names and values are labels.
     */

    public function create(array $roles): void
    {
        foreach ($roles as $roleData) {
            if ($this->tenants) {
                if($roleData['for_tenant']) {
                    Role::firstOrCreate([
                        'name' => $roleData['name'],
                    ]);
                }
            } else {
                if(!$roleData['for_tenant']) {
                    Role::firstOrCreate([
                        'name' => $roleData['name'],
                    ]);
                }
            }
        }
    }
}
