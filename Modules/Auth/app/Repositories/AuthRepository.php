<?php
namespace Modules\Auth\Repositories;

use Illuminate\Support\Facades\Hash;
use Modules\Auth\Data\RegisterData;
use Modules\Auth\Data\LoginData;
use Modules\User\Models\User;

class AuthRepository
{
    public function register(RegisterData $data): User
    {
        $user = User::create([
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
        ]);

        return $user;
    }

    public function login(LoginData $data): ?string
    {
        $user = User::where('email', $data->email)->first();

        if (! $user || ! Hash::check($data->password, $user->password)) {
            return null;
        }

        // create token name to identify client (e.g., api-token)
        return $user->createToken('api-token')->plainTextToken;
    }

    public function logout($user): void
    {
        // revoke current token
        $user->currentAccessToken()?->delete();
    }
}
