<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EndUserController extends Controller
{
    /**
     * "In Progress" — verified clients whose 1st round isn't done yet.
     * Once round 1 is complete a VA moves them to Clients, where the
     * remaining rounds are worked.
     */
    public function index(Request $request)
    {
        return $this->listView($request, 'in_progress');
    }

    /** "Clients" — the main working list; all rounds after the 1st happen here. */
    public function activeClients(Request $request)
    {
        return $this->listView($request, 'clients');
    }

    private function listView(Request $request, string $bucket)
    {
        $clientId = session('selected_client_id');

        $query = EndUser::forClient($clientId)
            ->notHeld()
            ->when($bucket === 'clients', fn ($q) => $q->done(), fn ($q) => $q->inProgress())
            // progress % is derived from the step log — eager load it so the
            // accessor doesn't fire a query per row
            ->with('processSteps:id,end_user_id,round,step_type')
            ->withCount([
                'processSteps',
                'processSteps as week1_count' => fn ($q) => $q->where('week', 1),
                'processSteps as week2_count' => fn ($q) => $q->where('week', 2),
                'processSteps as week3_count' => fn ($q) => $q->where('week', 3),
                'processSteps as week4_count' => fn ($q) => $q->where('week', 4),
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        $endUsers = $query->orderBy('start_date', 'asc')->orderBy('first_name')->get()
            ->sort(function ($a, $b) {
                $byIncomplete = ($b->is_incomplete ? 1 : 0) <=> ($a->is_incomplete ? 1 : 0);
                if ($byIncomplete !== 0) return $byIncomplete;
                return $a->start_date <=> $b->start_date;
            })
            ->values();

        // Optional column sort (pro console). Without it, the default ordering
        // above stands: incomplete files first, then oldest start date.
        $sortKeys = [
            'name'     => fn ($e) => mb_strtolower($e->full_name),
            'round'    => fn ($e) => $e->current_round,
            'started'  => fn ($e) => $e->current_round_start_date ?? '',
            'next'     => fn ($e) => $e->next_round_date ?? '',
            'days'     => fn ($e) => $e->days_left_in_round ?? PHP_INT_MAX,
            'status'   => fn ($e) => $e->status,
            'progress' => fn ($e) => $e->progress_percent,
        ];

        if ($key = $sortKeys[$request->query('sort')] ?? null) {
            $desc = $request->query('dir', 'asc') === 'desc';
            $endUsers = $endUsers->sortBy($key, SORT_REGULAR, $desc)->values();
        }

        $daysLeft = $endUsers->map->days_left_in_round->filter(fn ($d) => $d !== null);

        $stats = [
            'total'    => $endUsers->count(),
            'active'   => $endUsers->where('status', 'active')->count(),
            'paused'   => $endUsers->where('status', 'paused')->count(),
            'negative' => $daysLeft->filter(fn ($d) => $d < 0)->count(),
            'avg_days' => $daysLeft->isEmpty() ? 0 : (int) round($daysLeft->avg()),
        ];

        $view = $this->adminView('admin.end-users.index');

        // The pro console pages the table; the original view lists every row.
        if ($view !== 'admin.end-users.index') {
            $endUsers = $this->paginateCollection($endUsers, 50, $request);
        }

        return view($view, compact('endUsers', 'bucket', 'stats'));
    }

    /**
     * Download every active client's CFPB login for the selected business owner
     * as a CSV — the fast bulk alternative to clicking "Show" on each profile.
     *
     * Super-admin ONLY (enforced by the route's admin.super middleware): VAs must
     * never bulk-export credentials. cfpb_password is encrypted at rest and is
     * decrypted here on read. The file is streamed, never written to disk.
     */
    public function exportCfpb(Request $request)
    {
        $clientId = session('selected_client_id');
        $bo = Client::findOrFail($clientId);

        // Same set as the "Clients" list (round 1 done), but unpaginated — every one.
        $clients = EndUser::forClient($clientId)->done()
            ->orderBy('start_date', 'asc')
            ->orderBy('first_name')
            ->get();

        $filename = 'cfpb-logins-'
            . \Illuminate\Support\Str::slug($bo->business_name ?: 'business-owner')
            . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($clients) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads it correctly
            fputcsv($out, ['Client Name', 'CFPB Email', 'CFPB Password']);
            foreach ($clients as $eu) {
                fputcsv($out, [$eu->full_name, $eu->cfpb_email ?? '', $eu->cfpb_password ?? '']);
            }
            fclose($out);
        }, $filename, [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }

    /**
     * Download every active client's credit-monitoring login for the selected
     * business owner as a CSV. Super-admin only (route middleware); VAs never
     * bulk-export credentials. credit_monitoring_password is encrypted at rest
     * and decrypted here on read. Streamed, never written to disk.
     */
    public function exportCreditMonitoring(Request $request)
    {
        $clientId = session('selected_client_id');
        $bo = Client::findOrFail($clientId);

        $clients = EndUser::forClient($clientId)->done()
            ->orderBy('start_date', 'asc')
            ->orderBy('first_name')
            ->get();

        $filename = 'credit-monitoring-logins-'
            . \Illuminate\Support\Str::slug($bo->business_name ?: 'business-owner')
            . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($clients) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, ['Client Name', 'Service Name', 'Username / Email', 'Password']);
            foreach ($clients as $eu) {
                fputcsv($out, [
                    $eu->full_name,
                    $eu->credit_monitoring_name ?? '',
                    $eu->credit_monitoring_username ?? '',
                    $eu->credit_monitoring_password ?? '',
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedPayload($request, true);
        $data['client_id'] = session('selected_client_id');
        $data['status'] = 'active';

        $endUser = EndUser::create($data);

        $files = $this->handleFileUploads($request, $endUser);
        if ($files) {
            $endUser->update($files);
        }

        Message::postSystem(
            $endUser->client_id,
            "New client {$endUser->full_name} has been added. Start working on it."
        );

        return redirect()->route('admin.end-users.show', $endUser)->with('status', 'Client added.');
    }

    public function show(string $id)
    {
        $endUser = $this->scoped()->with([
            'client',
            'processSteps.documents',
            'processSteps.createdBy',
            'documents',
            'scoreHistory',
            'notes.createdBy',
        ])->findOrFail($id);

        return view('admin.end-users.show', compact('endUser'));
    }

    public function statusReport(string $id)
    {
        $endUser = $this->scoped()->with([
            'client',
            'processSteps.createdBy',
            'scoreHistory',
        ])->findOrFail($id);

        return view('admin.end-users.status-report', compact('endUser'));
    }

    public function update(Request $request, string $id)
    {
        $endUser = $this->scoped()->findOrFail($id);

        $data = $this->validatedPayload($request, false);

        // Inline "Round Started" edit: the date the current round began. For the
        // 1st round that IS the client's start_date; for a later round it lives in
        // round_dates[label]. Blank clears a later round's date (1st falls back to
        // no start).
        if ($request->has('round_started')) {
            $rs = $data['round_started'] ?? null;
            $label = $endUser->current_round_label;
            if ($label === '1st Round') {
                $data['start_date'] = $rs;
            } else {
                $dates = $endUser->round_dates ?? [];
                if ($rs) { $dates[$label] = $rs; } else { unset($dates[$label]); }
                $data['round_dates'] = $dates ?: null;
            }
            unset($data['round_started']);
        }

        // Inline "Next Round Date" edit: a manual override; blank reverts to auto.
        if ($request->has('next_round_override')) {
            $data['next_round_override'] = $data['next_round_override'] ?? null;
        }

        // Inline Round-Errors edit: the reason is stored in intake_review_note.
        if ($request->has('reason')) {
            $data['intake_review_note'] = $data['reason'] ?? null;
            unset($data['reason']);
        }

        // Strip empty secrets so they don't overwrite existing values
        if (array_key_exists('credit_monitoring_password', $data) && $data['credit_monitoring_password'] === null) {
            unset($data['credit_monitoring_password']);
        }
        if (array_key_exists('credit_monitoring_security_answer', $data) && $data['credit_monitoring_security_answer'] === null) {
            unset($data['credit_monitoring_security_answer']);
        }
        if (array_key_exists('cfpb_password', $data) && $data['cfpb_password'] === null) {
            unset($data['cfpb_password']);
        }
        if (array_key_exists('ssn', $data) && $data['ssn'] === null) {
            unset($data['ssn']);
        }

        // Rounds only persist when the submitting form includes the rounds section
        // (hidden `rounds_present` flag). This lets the inline status-only form leave
        // existing rounds untouched, while allowing the edit modal to clear them.
        if ($request->has('rounds_present')) {
            $newRounds = $request->input('rounds', []) ?: [];
            $data['rounds'] = $newRounds ?: null;

            // Auto-stamp the start date for any round that is now selected but
            // doesn't yet have a recorded date — captured server-side the moment
            // the round is started, no manual date entry. Existing dates are kept
            // (even if a round is later deselected) so history is never lost.
            $dates = $endUser->round_dates ?? [];
            foreach ($newRounds as $label) {
                if ($label !== '1st Round' && empty($dates[$label])) {
                    $dates[$label] = now()->toDateString();
                }
            }
            $data['round_dates'] = $dates ?: null;
        } else {
            unset($data['rounds']);
        }

        $files = $this->handleFileUploads($request, $endUser);
        $data = array_merge($data, $files);

        $endUser->update($data);

        // Fall back to the client's own page (never the site root) if the
        // referer is missing — some hosts strip it on multipart posts, and we
        // must not land the user on the homepage or picker after a save.
        return back(302, [], route('admin.end-users.show', $endUser->id))
            ->with('status', 'Client updated.');
    }

    public function destroy(string $id)
    {
        // Soft delete → Recycle Bin. Documents and files are kept for 10 days
        // so a super admin can restore the client; only a purge removes them.
        $endUser = $this->scoped()->findOrFail($id);
        $name = $endUser->full_name;
        $endUser->forceFill([
            'deleted_by_admin_id' => Auth::guard('admin')->id(),
            'deleted_with_owner'  => false,
        ])->save();
        $endUser->delete();

        return redirect()->route('admin.end-users.index')
            ->with('status', "Client {$name} moved to the Recycle Bin.");
    }

    /* ---------------- New Clients (intake submissions) ---------------- */

    public function newClients()
    {
        $client = Client::findOrFail(session('selected_client_id'));
        abort_unless($client->intake_enabled, 404);

        if (empty($client->intake_token)) {
            $client->update(['intake_token' => Client::generateIntakeToken()]);
        }

        $endUsers = EndUser::forClient($client->id)
            ->notHeld()
            ->where('intake_status', 'pending_review')
            ->orderByDesc('intake_submitted_at')
            ->get();

        return view($this->adminView('admin.end-users.new-clients'), ['endUsers' => $endUsers, 'client' => $client]);
    }

    /** Error clients — moved out of Clients with a VA-entered error to fix. */
    public function errors()
    {
        $endUsers = EndUser::forClient(session('selected_client_id'))
            ->notHeld()
            ->where('intake_status', 'error')
            ->orderByDesc('updated_at')
            ->get();

        return view($this->adminView('admin.end-users.errors'), compact('endUsers'));
    }

    /** Round Errors — clients past round 1 pulled out with an import problem. */
    public function roundErrors()
    {
        $endUsers = EndUser::forClient(session('selected_client_id'))
            ->notHeld()
            ->roundError()
            ->orderByDesc('updated_at')
            ->get();

        return view($this->adminView('admin.end-users.round-errors'), compact('endUsers'));
    }

    /** Move a Clients-list client into Round Errors with a type + reason. */
    public function moveToRoundError(Request $request, string $id)
    {
        $data = $request->validate([
            'error_type' => 'required|string|max:120',
            'reason'     => 'nullable|string|max:1000',
        ]);

        $endUser = $this->scoped()->findOrFail($id);
        $endUser->update([
            'intake_status'      => 'round_error',
            'error_type'         => $data['error_type'],
            'intake_review_note' => $data['reason'] ?? null,
        ]);

        return redirect()->back()
            ->with('status', "{$endUser->full_name} moved to Round Errors.");
    }

    /** Resolve a round error — send the client back to the Clients list. */
    public function resolveRoundError(string $id)
    {
        $endUser = $this->scoped()->findOrFail($id);
        $endUser->update([
            'intake_status'      => 'done',
            'error_type'         => null,
            'intake_review_note' => null,
        ]);

        return redirect()->back()
            ->with('status', "{$endUser->full_name} resolved — back in Clients.");
    }

    /** Hold / Pause — clients parked out of the normal buckets. */
    public function holdList()
    {
        $endUsers = EndUser::forClient(session('selected_client_id'))
            ->onHold()
            ->orderByDesc('held_at')
            ->get();

        return view($this->adminView('admin.end-users.hold'), compact('endUsers'));
    }

    /** Put a client on hold — hides them from their bucket until resumed. */
    public function hold(string $id)
    {
        $endUser = $this->scoped()->findOrFail($id);
        $endUser->update(['held_at' => now()]);

        return redirect()->back()
            ->with('status', "{$endUser->full_name} placed on Hold/Pause.");
    }

    /** Resume a held client — drops them back into their normal bucket. */
    public function resume(string $id)
    {
        $endUser = $this->scoped()->findOrFail($id);
        $endUser->update(['held_at' => null]);

        return redirect()->back()
            ->with('status', "{$endUser->full_name} resumed.");
    }

    public function approveIntake(string $id)
    {
        $endUser = $this->scoped()->findOrFail($id);

        // Move to In Progress. The round clock does NOT start here — it starts
        // when the client is moved into the Clients list (see moveToDone).
        $endUser->update(['intake_status' => 'approved', 'intake_review_note' => null]);

        return redirect()->back()
            ->with('status', "{$endUser->full_name} moved to In Progress.");
    }

    /**
     * Move a client into the main Clients list (1st round complete).
     * The round clock starts here — start_date is stamped on this move, so
     * Round Started / Next Round Date / Days Left count from today.
     */
    public function moveToDone(string $id)
    {
        $endUser = $this->scoped()->findOrFail($id);

        $endUser->update([
            'intake_status'      => 'done',
            'intake_review_note' => null,
            'start_date'         => now()->toDateString(),
        ]);

        return redirect()->back()
            ->with('status', "{$endUser->full_name} moved to Clients — round clock started.");
    }

    /** Move a client back into the New Clients (pending review) list — one click, no prompt. */
    public function moveToNewClients(string $id)
    {
        $endUser = $this->scoped()->findOrFail($id);
        $endUser->update(['intake_status' => 'pending_review', 'intake_review_note' => null]);

        return redirect()->back()
            ->with('status', "{$endUser->full_name} moved to New Clients.");
    }

    /** Move a client into the Errors bucket with a VA-entered error note. */
    public function moveToErrors(Request $request, string $id)
    {
        $endUser = $this->scoped()->findOrFail($id);
        $note = trim((string) $request->input('note', ''));

        $endUser->update([
            'intake_status'      => 'error',
            'intake_review_note' => $note !== '' ? $note : null,
        ]);

        return redirect()->route('admin.errors')
            ->with('status', "{$endUser->full_name} moved to Errors.");
    }

    public function regenerateIntake()
    {
        $client = Client::findOrFail(session('selected_client_id'));
        abort_unless($client->intake_enabled, 404);

        $client->update(['intake_token' => Client::generateIntakeToken()]);

        return redirect()->route('admin.new-clients')
            ->with('status', 'Intake link regenerated — the old link no longer works.');
    }

    /**
     * Generate (or regenerate) the server-to-server API key for the selected BO.
     * The hosted intake link stays active — a BO can use both their own funnel
     * (API) and the built-in intake form at the same time.
     */
    public function regenerateApiKey()
    {
        $client = Client::findOrFail(session('selected_client_id'));
        abort_unless($client->intake_enabled, 404);

        $client->update(['intake_api_key' => Client::generateApiKey()]);

        return redirect()->route('admin.new-clients')
            ->with('status', 'API key generated — copy it now and keep it secret.');
    }

    /** Delete one identity document (photo ID / proof of address / SSN / collage). */
    public function destroyIdentity(string $endUser, string $type)
    {
        $user = $this->scoped()->findOrFail($endUser);

        $column = match ($type) {
            'photo_id'         => 'photo_id_path',
            'proof_of_address' => 'proof_of_address_path',
            'ssn_picture'      => 'ssn_picture_path',
            'collage'          => 'collage_path',
            default            => abort(404),
        };

        if ($path = $user->{$column}) {
            if (Storage::disk('private')->exists($path)) {
                Storage::disk('private')->delete($path);
            }
            $user->update([$column => null]);
        }

        return back()->with('status', 'Identity document deleted.');
    }

    private function scoped()
    {
        return EndUser::forClient(session('selected_client_id'));
    }

    private function validatedPayload(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';
        $reqOrNullable = $creating ? 'required' : 'nullable';

        $rules = [
            'first_name'                  => "$req|string|max:100",
            'last_name'                   => "$req|string|max:100",
            'suffix'                      => "$req|in:None,Jr.,Sr.,I,II,III,IV,V",
            'email'                       => "$req|email|max:255",
            'phone'                       => "$reqOrNullable|string|max:30",
            'date_of_birth'               => "$reqOrNullable|date|before:today",
            'current_address'             => "$req|string|max:255",
            'city'                        => "$req|string|max:120",
            'state'                       => "$req|string|max:120",
            'zipcode'                     => "$req|string|max:20",
            'ssn'                         => "$reqOrNullable|string|max:32",
            'credit_monitoring_name'      => "$reqOrNullable|string|max:100",
            'credit_monitoring_username'  => "$reqOrNullable|string|max:255",
            'credit_monitoring_password'  => "$reqOrNullable|string|max:255",
            'credit_monitoring_security_answer' => "$reqOrNullable|string|max:255",
            'cfpb_email'                  => 'nullable|email|max:255',
            'cfpb_password'               => 'nullable|string|max:255',
            'start_date'                  => "$req|date",
            // Inline date edits from the Clients list (both optional).
            'round_started'               => 'sometimes|nullable|date',
            'next_round_override'         => 'sometimes|nullable|date',
            // Inline edits from the Round Errors list.
            'error_type'                  => 'sometimes|nullable|string|max:120',
            'reason'                      => 'sometimes|nullable|string|max:1000',
            'status'                      => 'sometimes|in:active,paused,graduated,cancelled',
            'rounds'                      => 'nullable|array|max:8',
            'rounds.*'                    => 'in:' . implode(',', EndUser::ROUND_OPTIONS),
            'collage'                     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];

        $data = $request->validate($rules);
        unset($data['collage']);
        return $data;
    }

    private function handleFileUploads(Request $request, EndUser $endUser): array
    {
        $out = [];

        foreach ([
            'collage' => 'collage_path',
        ] as $field => $column) {
            if ($request->hasFile($field)) {
                if ($endUser->{$column} && Storage::disk('private')->exists($endUser->{$column})) {
                    Storage::disk('private')->delete($endUser->{$column});
                }
                $file = $request->file($field);
                $filename = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
                $path = $file->storeAs("uploads/{$endUser->id}/identity", $filename, 'private');
                $out[$column] = $path;
            }
        }

        return $out;
    }
}
