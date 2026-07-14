<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSuperadmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasRole('superadmin')) {
            return redirect('/panel');
        }

        return $next($request);
    }
}
