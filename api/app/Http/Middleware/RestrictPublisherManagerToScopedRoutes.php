<?php

namespace App\Http\Middleware;

use App\Support\MessageLocalizer;

use App\Domain\Auth\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictPublisherManagerToScopedRoutes
{
    /**
     * Publisher managers may manage: their publisher's warehouses, books, employees,
     * orders, the shared author library, and cover/photo uploads. They may read categories,
     * publishers, and settings (needed for book forms), but must not access
     * customers, countries, or write global settings.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('employee')->user();
        if (! $user || ! UserRole::isPublisherScoped($user->role)) {
            return $next($request);
        }

        $path = $request->path();

        // Full access (create/edit/delete) — per-record ownership is enforced in controllers.
        $fullAccessPrefixes = ['books', 'authors', 'warehouses', 'employees', 'orders', 'pos', 'upload-cover', 'upload-author-photo'];
        foreach ($fullAccessPrefixes as $prefix) {
            if (str_contains($path, 'admin/'.$prefix)) {
                return $next($request);
            }
        }

        // Read-only helpers required by the book/warehouse forms.
        $readOnlyPrefixes = ['categories', 'publishers', 'settings'];
        foreach ($readOnlyPrefixes as $prefix) {
            if (str_contains($path, 'admin/'.$prefix) && $request->isMethod('GET')) {
                return $next($request);
            }
        }

        // Allow publisher settings PUT
        if (str_contains($path, 'admin/publishers/') && str_ends_with($path, '/settings') && $request->isMethod('PUT')) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => MessageLocalizer::localize('Forbidden. Publisher managers can only manage their publisher\'s warehouses, books, employees, authors, and orders.'),
            'data' => (object) [],
        ], 403);
    }
}
