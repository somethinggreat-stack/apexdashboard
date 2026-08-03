<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
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

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            // TEMP diagnostic: why do admin saves get bounced to login? Capture
            // whether PHP even received the session cookie (this host mangles
            // multipart requests), whether auth is present, and whether the CSRF
            // token matches. Logged to storage/logs/laravel-*.log.
            if (in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)
                && $request->is('admin/*')
                && ($e instanceof TokenMismatchException || $status === 419
                    || $e instanceof AuthenticationException || $status === 401)) {
                Log::warning('SAVE_DEBUG', [
                    'exception'               => class_basename($e),
                    'status'                  => $status,
                    'path'                    => $request->path(),
                    'method'                  => $request->method(),
                    'content_type'            => (string) $request->header('Content-Type'),
                    'session_cookie_received' => $request->cookies->has(config('session.cookie')),
                    'session_started'         => $request->hasSession() && $request->session()->isStarted(),
                    'admin_authed'            => auth('admin')->check(),
                    'form_token_len'          => strlen((string) $request->input('_token')),
                    'session_token_len'       => $request->hasSession() ? strlen((string) $request->session()->token()) : 0,
                    'token_matches'           => ($request->hasSession() && $request->input('_token'))
                        ? hash_equals((string) $request->session()->token(), (string) $request->input('_token'))
                        : null,
                ]);
            }

            // Session expired / CSRF token no longer valid.
            if ($e instanceof TokenMismatchException || $status === 419) {
                return redirect()->to($login)
                    ->with('status', 'Your session expired because the page was left open. Please sign in again.');
            }

            // Not (or no longer) signed in.
            if ($e instanceof AuthenticationException || $status === 401) {
                return redirect()->to($login)
                    ->with('status', 'Please sign in to continue.');
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
