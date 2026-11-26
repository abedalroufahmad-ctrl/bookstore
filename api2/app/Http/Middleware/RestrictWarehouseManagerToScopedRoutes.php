<?php

namespace App\Http\Middleware;

use App\Support\MessageLocalizer;

use App\Domain\Auth\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictWarehouseManagerToScopedRoutes
{
    /**
     * Warehouse managers may manage their warehouses, staff, orders, POS,
     * and books for their assigned warehouses (including cover analyze/upload).
     * They may read categories/publishers/settings for forms, but not change global settings.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('employee')->user();
        if (! $user || ! UserRole::isWarehouseScoped($user->role)) {
            return $next($request);
        }

        $path = $request->path();
        $allowedPrefixes = [
            'warehouses',
            'employees',
            'orders',
            'settings',
            'pos',
            'books',
            'authors',
            'upload-cover',
            'analyze-cover',
            'upload-author-photo',
        ];
        $isAllowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_contains($path, 'admin/'.$prefix)) {
                if ($prefix === 'settings' && $request->isMethod('PUT')) {
                    continue;
                }
                $isAllowed = true;
                break;
            }
        }

        if (! $isAllowed) {
            $readOnlyPrefixes = ['categories', 'publishers'];
            foreach ($readOnlyPrefixes as $prefix) {
                if (str_contains($path, 'admin/'.$prefix) && $request->isMethod('GET')) {
                    $isAllowed = true;
                    break;
                }
            }
        }

        if (! $isAllowed) {
            return response()->json([
                'success' => false,
                'message' => MessageLocalizer::localize('Forbidden. Warehouse managers can only manage their assigned warehouse, its employees, and its orders.'),
                'data' => (object) [],
            ], 403);
        }

        return $next($request);
    }
}
