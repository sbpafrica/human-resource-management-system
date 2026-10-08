<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $request->session()->save();

        $user = Auth::user();
        if ($user) {
            $sessionId = $request->session()->getId();
            $updated = DB::table('sessions')
                ->where('id', $sessionId)
                ->update([
                    'user_id' => $user->id,
                    'last_activity' => now()->timestamp,
                ]);
            Log::debug('Session after login', [
                'session_id' => $sessionId,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'updated_rows' => $updated,
                'last_activity' => now()->timestamp,
            ]);
        } else {
            Log::error('No user authenticated after login');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request)
    {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login'); // Full redirect = fresh page + fresh tokens
}
}
