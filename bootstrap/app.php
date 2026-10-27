<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
    $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

    $middleware->web(append: [
        HandleAppearance::class,
        HandleInertiaRequests::class,
        AddLinkHeadersForPreloadedAssets::class,
        \App\Http\Middleware\RefreshCsrfToken::class,
    ]);

    $middleware->validateCsrfTokens(except: [
        'payslips/*/send-email',
        'payslips/*/send-sms',
        'payslips/*/download-multiple',
        'receipts/verify-payment',
        'receipts/paystack-webhook',
    ]);
})

    ->withExceptions(function (Exceptions $exceptions) {

        // ── 419: CSRF / Session Expired ────────────────────────────────────
        // render() receives ($exception, $request) — correct for typed exceptions
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'message' => 'Your session has expired. Please refresh the page and log in again.',
                ], 419);
            }

            return redirect()->route('login')->with('error', 'Your session expired. Please log in again.');
        });

        // ── 401: Unauthenticated ───────────────────────────────────────────
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login')->with('error', 'Please log in to continue.');
        });

        // ── 404: Not Found ─────────────────────────────────────────────────
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->header('X-Inertia')) {
                return Inertia::render('errors/404', ['status' => 404])
                    ->toResponse($request)
                    ->setStatusCode(404);
            }

            return response()->view('errors.404', ['status' => 404], 404);
        });

        // ── 403: Forbidden ─────────────────────────────────────────────────
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->header('X-Inertia')) {
                return Inertia::render('errors/403', ['status' => 403])
                    ->toResponse($request)
                    ->setStatusCode(403);
            }

            return response()->view('errors.403', ['status' => 403], 403);
        });

        // ── 500: Generic Server Error ──────────────────────────────────────
        // Must be render() and must be last so specific handlers above fire first.
        $exceptions->render(function (\Throwable $e, Request $request) {
            // Let Laravel's debugger handle it in local/dev
            if (config('app.debug')) {
                return null;
            }

            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            // Don't re-handle statuses already covered above
            if (in_array($statusCode, [401, 403, 404, 419])) {
                return null;
            }

            if ($request->header('X-Inertia')) {
                return Inertia::render('errors/500', ['status' => $statusCode])
                    ->toResponse($request)
                    ->setStatusCode($statusCode);
            }

            return null;
        });

    })->create();
