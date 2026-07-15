<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogActivity
{
    /**
     * Only these routes are recorded in the audit trail — the major, meaningful
     * actions. Everything else (switching business owners, opening pages, etc.)
     * is intentionally NOT logged so the activity log stays clean and useful.
     * Login / logout are recorded separately in the AuthController.
     */
    private const ACTIONS = [
        'admin.new-clients.approve'    => 'Approved client → moved to Clients',
        'admin.end-users.to-errors'    => 'Moved client to Errors',
        'admin.end-users.store'        => 'Added a client',
        'admin.end-users.destroy'      => 'Deleted a client',
        'admin.documents.store'        => 'Uploaded a document',
        'admin.documents.bulk'         => 'Uploaded documents',
        'admin.new-clients.regenerate' => 'Regenerated the intake link',
        'admin.users.store'            => 'Added a user',
        'admin.users.destroy'          => 'Deleted a user',
        'admin.users.password'         => 'Reset a user password',
        // Leads pipeline
        'admin.prospect-leads.store'   => 'Added a lead',
        'admin.prospect-leads.move'    => 'Moved a lead to In Contact',
        'admin.prospect-leads.destroy' => 'Deleted a lead',
        'admin.prospects.store'        => 'Added a prospect',
        'admin.prospects.mark-interested' => 'Marked a lead interested',
        'admin.prospects.mark-lost'    => 'Marked a lead lost',
        'admin.prospects.reactivate'   => 'Reactivated a lead',
        // Extra projects (funnels / customer support / ads)
        'admin.extra.store'            => 'Added an extra project',
        'admin.extra.update'           => 'Updated an extra project',
        'admin.extra.destroy'          => 'Deleted an extra project',
    ];

    public function handle(Request $request, Closure $next)
    {
        // Capture the client's name BEFORE a delete runs — afterwards it's gone,
        // so the audit trail would otherwise only have their internal id.
        $deletedName = null;
        if (optional($request->route())->getName() === 'admin.end-users.destroy') {
            $deletedName = optional(\App\Models\EndUser::find($request->route('id')))->full_name;
        }

        $response = $next($request);

        try {
            $name = optional($request->route())->getName();

            if ($name
                && isset(self::ACTIONS[$name])
                && $response->getStatusCode() < 400
                && Auth::guard('admin')->check()) {
                $description = self::ACTIONS[$name];
                if ($name === 'admin.end-users.destroy' && $deletedName) {
                    $description .= ": {$deletedName}";
                }
                ActivityLog::create([
                    'admin_id'    => Auth::guard('admin')->id(),
                    'action'      => $name,
                    'description' => $description,
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

    private function subject(Request $request): ?string
    {
        $id = $request->session()->get('selected_client_id');
        if (!$id) {
            return null;
        }

        return optional(Client::find($id))->business_name;
    }
}
