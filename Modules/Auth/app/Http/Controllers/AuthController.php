<?php
namespace Modules\Auth\app\Http\Controllers;

use Illuminate\Routing\Controller;
use App\Http\Resources\MainResource;
use Modules\Auth\app\Http\Requests\RegisterRequest;
use Modules\Auth\app\Http\Requests\LoginRequest;
use Modules\Auth\app\Data\RegisterData;
use Modules\Auth\app\Data\LoginData;
use Modules\User\app\Models\User;

use Modules\Auth\app\Repositories\AuthRepository;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class AuthController extends Controller
{
    public function __construct(private AuthRepository $repository) {}

    public function register(RegisterRequest $request): MainResource
    {
        $data = RegisterData::from($request->validated());
        $user = $this->repository->register($data);
        $token = $user->createToken('api-token')->plainTextToken;

        return MainResource::make([
            'user' => $user,
            'token' => $token,
        ], 'Registered', ResponseAlias::HTTP_CREATED);
    }

    public function login(LoginRequest $request): MainResource
    {
        $data = LoginData::from($request->validated());
        $token = $this->repository->login($data);

        if (! $token) {
            return MainResource::make(null, 'Invalid credentials', ResponseAlias::HTTP_UNAUTHORIZED);
        }

        $user = auth()->user() ?? User::where('email', $data->email)->first();

        return MainResource::make([
            'user' => $user,
            'token' => $token,
        ], 'Logged in');
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
