<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\MainResource;
use Modules\Auth\Http\Requests\RegisterRequest;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Auth\Data\RegisterData;
use Modules\Auth\Data\LoginData;
use Modules\Auth\Http\Resources\AuthResource;
use Modules\Auth\Repositories\AuthRepository;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use OpenApi\Annotations as OA;

class AuthController extends Controller
{
    public function __construct(private AuthRepository $repository) {}

    /**
     * @OA\Post(
     *     path="/api/v1/register",
     *     tags={"Auth"},
     *     summary="Register new user",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"first_name","last_name","email","password"},
     *
     *             @OA\Property(
     *                 property="first_name",
     *                 type="string",
     *                 example="John"
     *             ),
     *             @OA\Property(
     *                 property="last_name",
     *                 type="string",
     *                 example="Doe"
     *             ),
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 example="user1@test.com"
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 example="12345678"
     *             ),
     *             @OA\Property(
     *                 property="password_confirmation",
     *                 type="string",
     *                 example="12345678"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Registered successfully",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Registered"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *
     *                 @OA\Property(
     *                     property="user",
     *                     type="object"
     *                 ),
     *
     *                 @OA\Property(
     *                     property="token",
     *                     type="string",
     *                     example="1|xxxxxxxxxxxx"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function register(RegisterRequest $request): MainResource
    {
        $data = RegisterData::from($request->validated());
        $result = $this->repository->register($data);

        return MainResource::make(
            new AuthResource($result),
            'Registered'
        );
    }


    /**
     * @OA\Post(
     *     path="/api/v1/login",
     *     tags={"Auth"},
     *     summary="Login user",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 example="user1@test.com"
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 example="12345678"
     *             )
     *   
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Logged in successfully",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Logged in"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *
     *                 @OA\Property(
     *                     property="user",
     *                     type="object"
     *                 ),
     *
     *                 @OA\Property(
     *                     property="token",
     *                     type="string",
     *                     example="1|xxxxxxxxxxxx"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials"
     *     )
     * )
     */
    public function login(LoginRequest $request): MainResource
    {
        $data = LoginData::from($request->validated());

        $result = $this->repository->login($data);

        if (! $result) {
            return MainResource::make(
                null,
                'Invalid credentials',
                ResponseAlias::HTTP_UNAUTHORIZED
            );
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
