<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  string  ...$args  Allowed roles (e.g. 'manager', 'shipping') and optionally guard as last param
     */
    public function handle(Request $request, Closure $next, string ...$args): Response
    {
        $guards = ['employee', 'customer', 'api'];
        $guard = null;
        $roles = $args;

        if (count($args) > 1 && in_array(last($args), $guards, true)) {
            $guard = array_pop($roles);
        }

        $user = $guard ? auth($guard)->user() : $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => (object) [],
            ], 401);
        }

        $userRole = $user->role ?? null;

        if (! $userRole || ! in_array($userRole, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Insufficient role.',
                'data' => (object) [],
            ], 403);
        }

        return $next($request);
    }
}
