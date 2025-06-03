<?php


namespace Fp\FullRoute\Services\DevelopmentSetup;

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
        foreach ($users as $username => $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => ucfirst($username),
                    'password' => Hash::make($data['password']),
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
