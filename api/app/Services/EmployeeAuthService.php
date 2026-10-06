<?php

namespace App\Services;

use App\Domain\Auth\Interfaces\EmployeeAuthServiceInterface;

class EmployeeAuthService extends BaseService implements EmployeeAuthServiceInterface
{
    public function attemptLogin(array $credentials): string|false
    {
        return auth('employee')->attempt($credentials);
    }
}
