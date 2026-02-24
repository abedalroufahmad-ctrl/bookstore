<?php

namespace App\Http\Controllers\Api;

use App\Domain\Auth\Interfaces\CustomerAuthServiceInterface;
use App\Http\Requests\Customer\CustomerLoginRequest;
use App\Http\Requests\Customer\CustomerProfileUpdateRequest;
use App\Http\Requests\Customer\CustomerRegisterRequest;
use Illuminate\Http\JsonResponse;

class CustomerAuthController extends BaseApiController
{
    public function __construct(
        private CustomerAuthServiceInterface $authService
    ) {}

    public function register(CustomerRegisterRequest $request): JsonResponse
    {
        $customer = $this->authService->register($request->validated());

        return $this->successResponse([
            'customer' => $customer,
            'token' => $this->authService->tokenForUser($customer),
            'token_type' => 'bearer',
            'expires_in' => auth('customer')->factory()->getTTL() * 60,
        ], 'Registration successful', 201);
    }

    public function login(CustomerLoginRequest $request): JsonResponse
    {
        $token = $this->authService->attemptLogin($request->only('email', 'password'));

        if (! $token) {
            return $this->errorResponse('Invalid credentials', 401);
        }

        return $this->successResponse([
            'customer' => auth('customer')->user(),
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('customer')->factory()->getTTL() * 60,
        ], 'Login successful');
    }

    public function logout(): JsonResponse
    {
        auth('customer')->logout();

        return $this->successResponse(null, 'Successfully logged out');
    }

    public function refresh(): JsonResponse
    {
        $token = auth('customer')->refresh();

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('customer')->factory()->getTTL() * 60,
        ], 'Token refreshed');
    }

    public function me(): JsonResponse
    {
        return $this->successResponse(auth('customer')->user());
    }

    public function updateProfile(CustomerProfileUpdateRequest $request): JsonResponse
    {
        $customer = $this->authService->updateProfile(auth('customer')->user(), $request->validated());

        return $this->successResponse($customer, 'Profile updated successfully');
    }
}
