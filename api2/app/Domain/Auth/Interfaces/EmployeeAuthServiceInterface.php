<?php

namespace App\Domain\Auth\Interfaces;

interface EmployeeAuthServiceInterface
{
    /**
     * Attempt to authenticate an employee and return JWT token.
     */
    public function attemptLogin(array $credentials): string|false;
}
