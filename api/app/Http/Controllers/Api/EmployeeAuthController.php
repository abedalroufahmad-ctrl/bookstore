<?php

namespace App\Http\Controllers\Api;

use App\Domain\Auth\Interfaces\EmployeeAuthServiceInterface;
use App\Http\Requests\Employee\EmployeeLoginRequest;
use Illuminate\Http\JsonResponse;

class EmployeeAuthController extends BaseApiController
{
    public function __construct(
        private EmployeeAuthServiceInterface $authService
    ) {}

    public function login(EmployeeLoginRequest $request): JsonResponse
    {
        $token = $this->authService->attemptLogin($request->only('email', 'password'));

        if (! $token) {
            return $this->errorResponse('Invalid credentials', 401);
        }

        return $this->successResponse([
            'employee' => auth('employee')->user(),
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('employee')->factory()->getTTL() * 60,
        ], 'Login successful');
    }

    public function logout(): JsonResponse
    {
        auth('employee')->logout();

        return $this->successResponse(null, 'Successfully logged out');
    }

    public function me(): JsonResponse
    {
        return $this->successResponse(auth('employee')->user());
    }

    public function refresh(): JsonResponse
    {
        $token = auth('employee')->refresh();

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('employee')->factory()->getTTL() * 60,
        ], 'Token refreshed');
    }
}
