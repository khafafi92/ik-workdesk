<?php

namespace App\Http\Middleware;

use App\Services\ModuleAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $action = $request->route()?->getActionName();
        $module = $action
            ? app(ModuleAccessService::class)->moduleForAction($action)
            : null;

        if (
            $module !== null
            && ! app(ModuleAccessService::class)->isEnabled($module['label'])
        ) {
            abort(403, 'Modul ini sedang dinonaktifkan.');
        }

        return $next($request);
    }
}
