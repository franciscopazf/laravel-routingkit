<?php


namespace Rk\RoutingKit\Features\RolesAndPermissionsFeature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserCreator
{
    public static function make(): self
    {
        return new self();
    }

    /**
     * Create users based on the provided array.
     *
     * @param array $users An associative array where keys are usernames and values are arrays with 'email' and 'password'.
     */
    public function create(array $users): void
    {
        $userModel = config('routingkit.user_model', User::class);

        foreach ($users as $username => $data) {
            $userModel::updateOrCreate(
                ['email' => $data['user']['email']],
                $data['user']
            );
        }
    }
}
