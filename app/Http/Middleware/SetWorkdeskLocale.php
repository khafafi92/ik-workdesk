<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetWorkdeskLocale
{
    public const LOCALES = ['id', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $previousLocale = app()->getLocale();
        $locale = $request->user()?->preferred_locale;

        if (in_array($locale, self::LOCALES, true) && $locale !== $previousLocale) {
            app()->setLocale($locale);
        }

        try {
            return $next($request);
        } finally {
            if (app()->getLocale() !== $previousLocale) {
                app()->setLocale($previousLocale);
            }
        }
    }
}
