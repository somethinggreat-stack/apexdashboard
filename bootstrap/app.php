<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
        ]);

        // Server-to-server intake API (key-authenticated) — not a browser form.
        $middleware->validateCsrfTokens(except: ['api/intake']);

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
        //
    })->create();
