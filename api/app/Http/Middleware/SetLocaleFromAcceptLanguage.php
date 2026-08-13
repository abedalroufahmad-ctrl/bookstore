<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets app locale from Accept-Language (ar / en). Defaults to English for API.
 */
class SetLocaleFromAcceptLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = strtolower((string) $request->header('Accept-Language', ''));
        $locale = str_starts_with($header, 'ar') ? 'ar' : 'en';

        // Explicit query/header override used by clients
        $forced = strtolower((string) $request->header('X-Locale', $request->query('lang', '')));
        if (in_array($forced, ['ar', 'en'], true)) {
            $locale = $forced;
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
