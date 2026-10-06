<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Enums\UserRole;
use App\Support\MessageLocalizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictDirectSalesToScopedRoutes
{
    /**
     * Direct-sales staff may only use POS (invoices/reports/catalog) and read
     * their assigned warehouses. They must not reach online orders or catalog admin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('employee')->user();
        if (! $user || $user->role !== UserRole::DirectSales->value) {
            return $next($request);
        }

        $path = $request->path();

        if (str_contains($path, 'admin/pos')) {
            return $next($request);
        }

        if (str_contains($path, 'admin/warehouses') && $request->isMethod('GET')) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => MessageLocalizer::localize(
                'Forbidden. Direct sales staff can only access POS invoices and their assigned warehouses.'
            ),
            'data' => (object) [],
        ], 403);
    }
}
