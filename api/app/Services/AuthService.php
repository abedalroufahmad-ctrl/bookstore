<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService extends BaseService
{
    public function register(array $data): User
    {
        $data['password'] = Hash::make($data['password']);
        $data['role'] = $data['role'] ?? 'user';

        return User::create($data);
    }

    public function tokenForUser(User $user): string
    {
        return auth('api')->login($user);
    }

    public function attemptLogin(array $credentials): string|false
    {
        return auth('api')->attempt($credentials);
    }
}
