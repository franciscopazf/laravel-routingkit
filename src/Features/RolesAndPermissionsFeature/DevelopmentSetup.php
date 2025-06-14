<?php

namespace Fp\RoutingKit\Services\DevelopmentSetup;

use App\Models\User;
use Fp\RoutingKit\Entities\FpRoute;
use Fp\RoutingKit\Services\DevelopmentSetup\PermissionCreator;
use Fp\RoutingKit\Services\DevelopmentSetup\RoleCreator;
use Fp\RoutingKit\Services\DevelopmentSetup\RoleAssigner;
use Fp\RoutingKit\Services\DevelopmentSetup\UserCreator;

class DevelopmentSetup
{
    protected RoleCreator $roleCreator;
    protected UserCreator $userCreator;
    protected RoleAssigner $roleAssigner;
    protected PermissionCreator $permissionCreator;

    private function __construct(
        RoleCreator $roleCreator,
        UserCreator $userCreator,
        RoleAssigner $roleAssigner,
        PermissionCreator $permissionCreator
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
    public static function make(): self
    {
        return new self(
            RoleCreator::make(),
            UserCreator::make(),
            RoleAssigner::make(),
            PermissionCreator::make()
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
        $roles = config('fproute.roles');
        $users = config('fproute.development_users');

        $this->roleCreator
            ->create($roles);

        $this->userCreator
            ->create($users);

        $this->roleAssigner
            ->deletePermissionsOfRoles($roles)
            ->assign($users);

        //dd(User::all());

        $this->permissionCreator
            ->rebuildAll(FpRoute::class);
    }
}
