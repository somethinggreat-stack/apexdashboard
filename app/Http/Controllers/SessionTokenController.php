<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Hands the page a current CSRF token. Every page pings this quietly (every
 * 20 min while visible, and when the tab or phone comes back into focus) so a
 * form left open never submits a stale token; login forms fetch a fresh token
 * right before they submit. Calling it also keeps the session alive.
 */
class SessionTokenController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()
            ->json(['token' => csrf_token()])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
