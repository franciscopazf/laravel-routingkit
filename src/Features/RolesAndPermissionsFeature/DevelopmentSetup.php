<?php

namespace Rk\RoutingKit\Features\RolesAndPermissionsFeature;

use App\Models\User;
use Rk\RoutingKit\Entities\RkRoute;
use Rk\RoutingKit\Features\RolesAndPermissionsFeature\PermissionCreator;
use Rk\RoutingKit\Features\RolesAndPermissionsFeature\RoleCreator;
use Rk\RoutingKit\Features\RolesAndPermissionsFeature\RoleAssigner;
use Rk\RoutingKit\Features\RolesAndPermissionsFeature\UserCreator;

class DevelopmentSetup
{
    protected ?string $tenantId;
    protected ?bool$tenants;

    protected RoleCreator $roleCreator;
    protected UserCreator $userCreator;
    protected RoleAssigner $roleAssigner;
    protected PermissionCreator $permissionCreator;

    private function __construct(
        RoleCreator $roleCreator,
        UserCreator $userCreator,
        RoleAssigner $roleAssigner,
        PermissionCreator $permissionCreator,
        ?string $tenantId = null,
        ?bool $tenants = null
    ) {
        $this->roleCreator = $roleCreator;
        $this->userCreator = $userCreator;
        $this->roleAssigner = $roleAssigner;
        $this->permissionCreator = $permissionCreator;
    }

    /**
     * Create a new instance of DevelopmentSetup.
     *
     * This method is used to create an instance of the DevelopmentSetup service.
     *
     * @return self
     */
    public static function make(
        ?string $tenantId,
        ?bool $tenants
    ): self {


        return new self(
            RoleCreator::make($tenantId, $tenants),
            UserCreator::make($tenantId, $tenants),
            RoleAssigner::make($tenantId, $tenants),
            PermissionCreator::make($tenantId, $tenants),
            $tenantId,
            $tenants    
        );
    }

   

    /**
     * Run the development setup.
     *
     * This method creates roles, users, and assigns roles to users based on the configuration.
     *
     * @return void
     */
    public function run(): void
    {
        //dd('Running development setup...');
        $roles = config('routingkit.roles');
        $users = config('routingkit.development_users');

        $this->roleCreator
            ->create($roles);

        $this->userCreator
            ->create($users);

        $this->roleAssigner
            ->deletePermissionsOfRoles($roles)
            ->assign($users);

        //dd(User::all());

        $this->permissionCreator
            ->rebuildAll(RkRoute::class);
    }
}
