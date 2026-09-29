<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireLtroAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->hasPermission('ltro.view'), 403);

        return $next($request);
    }
}
