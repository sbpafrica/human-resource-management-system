<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PasswordResetLinkController extends Controller
{
    /**
     * Show the password reset link request page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        Log::info('PASSWORD RESET: request received', [
            'email' => $request->email,
        ]);

        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );

            Log::info('PASSWORD RESET: Password broker response', [
                'email' => $request->email,
                'status' => $status,
            ]);

            if ($status === Password::RESET_LINK_SENT) {
                return back()->with(
                    'status',
                    __('A password reset link has been sent to your email address.')
                );
            }

            Log::warning('PASSWORD RESET: broker did not send link', [
                'email' => $request->email,
                'status' => $status,
            ]);

            return back()->withErrors([
                'email' => __($status),
            ]);

        } catch (Throwable $e) {

            Log::error('PASSWORD RESET: email sending failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return back()->withErrors([
                'email' => 'Unable to send the password reset email at this time. Please contact the administrator.',
            ]);
        }
    }
}