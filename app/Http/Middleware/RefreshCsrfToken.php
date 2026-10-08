<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class RefreshCsrfToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only refresh CSRF cookie for guests (after logout or session expiry)
        if (! $request->user()) {
            // Laravel 12 way: Use the Cookie facade directly
            $response->withCookie(
                Cookie::make('XSRF-TOKEN', csrf_token(), 60 * 24, '/',
                    $request->getHost(), $request->secure(), false, false, 'lax')
            );
        }

        return $response;
    }
}
