<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictWarehouseManagerToScopedRoutes
{
    /**
     * Warehouse managers may only access: warehouses, employees, orders, and GET settings.
     * They must not access: books, authors, categories, customers, uploads, or PUT settings.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('employee')->user();
        if (! $user || ! UserRole::isWarehouseScoped($user->role)) {
            return $next($request);
        }

        $path = $request->path();
        $allowedPrefixes = ['warehouses', 'employees', 'orders', 'settings'];
        $isAllowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_contains($path, 'admin/' . $prefix)) {
                if ($prefix === 'settings' && $request->isMethod('PUT')) {
                    continue;
                }
                $isAllowed = true;
                break;
            }
        }

        if (! $isAllowed) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Warehouse managers can only manage their assigned warehouse, its employees, and its orders.',
                'data' => (object) [],
            ], 403);
        }

        return $next($request);
    }
}
