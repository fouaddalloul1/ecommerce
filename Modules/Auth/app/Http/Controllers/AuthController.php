<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\MainResource;
use Modules\Auth\Http\Requests\RegisterRequest;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Auth\Data\RegisterData;
use Modules\Auth\Data\LoginData;
use Modules\User\Models\User;
use Illuminate\Support\Facades\Auth;
use Modules\Auth\Http\Resources\AuthResource;
use Modules\Auth\Http\Resources\LoginResource;
use Modules\Auth\Repositories\AuthRepository;
use Modules\User\Http\Resources\UserResource;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class AuthController extends Controller
{
    public function __construct(private AuthRepository $repository) {}

    public function register(RegisterRequest $request): MainResource
    {
        $data = RegisterData::from($request->validated());
        $result = $this->repository->register($data);
        return MainResource::make(
            new AuthResource($result),
            'Registered'
        );
    }
    public function login(LoginRequest $request): MainResource
    {
        $data = LoginData::from($request->validated());

        $result = $this->repository->login($data);

        if (! $result) {
            return MainResource::make(null, 'Invalid credentials', ResponseAlias::HTTP_UNAUTHORIZED);
        }

        return MainResource::make(
            new AuthResource($result),
            'Logged in'
        );
    }

    public function logout(): MainResource
    {
        $this->repository->logout(auth()->user());
        return MainResource::make(null, 'Logged out');
    }

    public function me(): MainResource
    {
        return MainResource::make(auth()->user());
    }
}
