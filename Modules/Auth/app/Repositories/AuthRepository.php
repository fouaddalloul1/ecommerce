<?php

namespace Modules\Auth\Repositories;

use Illuminate\Support\Facades\Hash;
use Modules\Auth\Data\RegisterData;
use Modules\Auth\Data\LoginData;
use Modules\User\Models\User;

class AuthRepository
{
    public function register(RegisterData $data): array
    {
        $user = User::create([
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
        ]);
        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function login(LoginData $data): ?array
    {
        $user = User::where('email', $data->email)->first();

        if (! $user || ! Hash::check($data->password, $user->password)) {
            return null;
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'user'  => $user,
            'token' => $token,
        ];
    }


    public function logout($user): void
    {
        // revoke current token
        $user->currentAccessToken()?->delete();
    }

    public function me(): User
    {
        return User::query()
            ->select([
                'id',
                'first_name',
                'last_name',
                'email',
                'balance',
            ])
            ->findOrFail(auth()->id());
    }
}
