<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\TenantService;
use Illuminate\Support\Facades\Auth;

class TenantMiddleware
{
    public function handle($request, Closure $next)
    {
        $dbname = $request->route('client_dbname') // or however you get it
               ?? session('current_client_dbname')
               ?? 'placeholder';

        TenantService::switchToTenant($dbname);

        return $next($request);
    }
}
