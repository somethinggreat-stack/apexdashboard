<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'client.selected' => \App\Http\Middleware\EnsureClientSelected::class,
            'admin.super'     => \App\Http\Middleware\RoleSuper::class,
            'admin.leads'     => \App\Http\Middleware\RoleLeads::class,
            'admin.clients'   => \App\Http\Middleware\RoleClients::class,
        ]);

        // Server-to-server intake API (key-authenticated) — not a browser form.
        // Server-to-server intake endpoints are key-authenticated, not session-based.
        // 'partner-intake' is the same endpoint off the /api prefix (WAF workaround).
        $middleware->validateCsrfTokens(except: ['api/intake', 'partner-intake']);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('business-owner') || $request->is('business-owner/*')) {
                return route('client.login');
            }
            return route('admin.login');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('business-owner') || $request->is('business-owner/*')) {
                return route('client.dashboard');
            }
            return route('admin.client-selector.index');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /**
         * Nobody in the console should ever see a raw error screen. The common
         * one is an expired session: leave a tab open past the session lifetime,
         * click something, and Laravel throws 419 "Page Expired". Instead we send
         * them to the right login with a plain explanation. Real failures are
         * still logged in full — we only change what the human sees.
         */
        $exceptions->render(function (Throwable $e, Request $request) {
            // Never touch the key-authenticated intake API or any JSON caller —
            // those must keep receiving real status codes.
            if ($request->expectsJson() || $request->is('api/*') || $request->is('partner-intake')) {
                return null;
            }

            // Developers still get the real error locally.
            if (config('app.debug')) {
                return null;
            }

            $isPortal = $request->is('business-owner') || $request->is('business-owner/*');
            $login    = $isPortal ? route('client.login') : route('admin.login');

            // Already on a login page — let it render normally, never loop.
            if ($request->is('admin/login') || $request->is('business-owner/login')) {
                return null;
            }

            // A failed form validation is NOT a server error. Let Laravel handle
            // it the normal way: redirect back to the form with the field errors
            // shown. (Before this, ValidationException isn't an HttpException, so
            // it fell through as a 500 and bounced the user to login → Select
            // Business Owner — with no hint that a field was invalid. That was the
            // "CFPB save silently reverts" bug when a client had a blank address.)
            if ($e instanceof ValidationException) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            // Session expired / CSRF token no longer valid.
            if ($e instanceof TokenMismatchException || $status === 419) {
                return redirect()->to($login)
                    ->with('status', 'Your session expired (the page was left open too long). Please sign in again — your data is safe.');
            }

            // Not (or no longer) signed in — covers a normal guest AND everyone
            // after a deploy, which clears all sessions on purpose. Reassure the
            // team this is expected, not the old save bug.
            if ($e instanceof AuthenticationException || $status === 401) {
                return redirect()->to($login)
                    ->with('status', 'You’ve been signed out. Please sign in again to continue — nothing was lost.');
            }

            // Anything genuinely broken: log it in full, then hand the user a
            // clean landing spot instead of a 500 screen.
            if ($status >= 500) {
                Log::error('Unhandled error — user redirected to login', [
                    'path'      => $request->fullUrl(),
                    'method'    => $request->method(),
                    'exception' => get_class($e),
                    'message'   => $e->getMessage(),
                    'file'      => $e->getFile() . ':' . $e->getLine(),
                ]);

                return redirect()->to($login)
                    ->with('status', 'Something went wrong on our end and you were signed out for safety. Please sign in again.');
            }

            return null;   // 403/404 etc. keep their normal pages
        });
    })->create();
