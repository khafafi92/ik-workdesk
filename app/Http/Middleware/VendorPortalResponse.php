<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class VendorPortalResponse
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $response = $next($request);
        } catch (ModelNotFoundException $exception) {
            $response = response()->view('vendor-portal.unavailable', ['status' => 404], 404);
        } catch (HttpExceptionInterface $exception) {
            $status = $exception->getStatusCode();
            if (! in_array($status, [404, 409, 410])) {
                throw $exception;
            }
            $response = response()->view('vendor-portal.unavailable', ['status' => $status], $status);
        }
        if (in_array($response->getStatusCode(), [404, 409, 410])) {
            $response = response()->view('vendor-portal.unavailable', ['status' => $response->getStatusCode()], $response->getStatusCode());
        }
        foreach (['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY', 'X-Robots-Tag' => 'noindex, nofollow'] as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
