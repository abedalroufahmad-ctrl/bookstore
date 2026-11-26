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
    public static function apply(Request $request): void
    {
        $header = strtolower((string) $request->header('Accept-Language', ''));
        $locale = str_starts_with($header, 'ar') ? 'ar' : 'en';

        $forced = strtolower((string) $request->header('X-Locale', $request->query('lang', '')));
        if (in_array($forced, ['ar', 'en'], true)) {
            $locale = $forced;
        }

        app()->setLocale($locale);
    }

    public function handle(Request $request, Closure $next): Response
    {
        self::apply($request);

        return $next($request);
    }
}
