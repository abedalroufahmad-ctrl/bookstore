<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class AuthController extends BaseApiController
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());

        return $this->successResponse([
            'user' => $user,
            'token' => $this->authService->tokenForUser($user),
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ], 'User registered successfully', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $token = $this->authService->attemptLogin($request->only('email', 'password'));

        if (! $token) {
            return $this->errorResponse('Invalid credentials', 401);
        }

        return $this->successResponse([
            'user' => auth('api')->user(),
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }

    public function me(): JsonResponse
    {
        return $this->successResponse(auth('api')->user());
    }

    public function refresh(): JsonResponse
    {
        $token = auth('api')->refresh();

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return $this->successResponse(null, 'Successfully logged out');
    }
}
