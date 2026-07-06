<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogActivity
{
    /** Records every mutating admin action for the audit trail. */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
                && Auth::guard('admin')->check()) {
                ActivityLog::create([
                    'admin_id'    => Auth::guard('admin')->id(),
                    'action'      => optional($request->route())->getName(),
                    'description' => $this->describe($request),
                    'method'      => $request->method(),
                    'path'        => '/' . ltrim($request->path(), '/'),
                    'subject'     => $this->subject($request),
                    'ip'          => $request->ip(),
                ]);
            }
        } catch (\Throwable $e) {
            // Never let logging break the request.
        }

        return $response;
    }

    private function describe(Request $request): string
    {
        $name = optional($request->route())->getName();
        if (!$name) {
            return strtoupper($request->method()) . ' ' . $request->path();
        }
        $clean = str_replace(['admin.', '-', '.'], ['', ' ', ' '], $name);
        return ucfirst(trim($clean));
    }

    private function subject(Request $request): ?string
    {
        $id = $request->session()->get('selected_client_id');
        if (!$id) {
            return null;
        }
        return optional(Client::find($id))->business_name;
    }
}
