<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EndUser;
use App\Models\NegativeItem;
use App\Models\ProcessStep;
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

    /**
     * Super-admin bulk fix: for the selected business owner, log the missing
     * WEEKLY steps for every flagged (incomplete-log) client so the nags clear.
     * It NEVER logs the closeout steps (Pull Latest Report / Record Deletions) —
     * those stay a manual, past-due-only task in every scenario.
     */
    public function clearIncomplete(Request $request)
    {
        abort_unless(Auth::guard('admin')->user()?->isSuper(), 403);

        $clientId = session('selected_client_id');
        $adminId  = Auth::guard('admin')->id();

        // Every actively-worked client (In Progress + Done), not on hold.
        $clients = EndUser::forClient($clientId)
            ->notHeld()
            ->where(fn ($q) => $q->whereNull('intake_status')
                ->orWhereNotIn('intake_status', ['pending_review', 'error', 'round_error']))
            ->with('processSteps:id,end_user_id,round,week,step_type')
            ->withCount([
                'processSteps as week1_count' => fn ($q) => $q->where('week', 1),
                'processSteps as week2_count' => fn ($q) => $q->where('week', 2),
                'processSteps as week3_count' => fn ($q) => $q->where('week', 3),
                'processSteps as week4_count' => fn ($q) => $q->where('week', 4),
            ])
            ->get();

        $today  = now()->toDateString();
        $logged = 0;

        foreach ($clients as $eu) {
            $wk     = $eu->roundWeekLength();
            $count  = $eu->roundWeekCount();
            $round  = $eu->current_round;
            $days   = $eu->days_active;
            $byWeek = ProcessStep::stepTypesByWeek($eu->roundCycleDays());

            for ($w = 1; $w <= $count; $w++) {
                // Only the regular (non-closeout) steps of the week — the closeout
                // steps are deliberately never logged by this button.
                $regular = array_diff(array_keys($byWeek[$w] ?? []), EndUser::CLOSEOUT_STEPS);
                if (empty($regular)) {
                    continue;
                }
                $dueDay = (($w - 1) * $wk) + 1;
                if ($days < $dueDay || (int) ($eu->{"week{$w}_count"} ?? 0) !== 0) {
                    continue;   // not due yet, or already has a step for this week
                }

                $already = $eu->processSteps->where('round', $round)->where('week', $w)->pluck('step_type')->all();
                foreach ($regular as $type) {
                    if (in_array($type, $already, true)) {
                        continue;
                    }
                    ProcessStep::create([
                        'end_user_id'         => $eu->id,
                        'round'               => $round,
                        'week'                => $w,
                        'step_type'           => $type,
                        'step_date'           => $today,
                        'created_by_admin_id' => $adminId,
                    ]);
                    $logged++;
                }
            }
        }

        return back()->with('confirm', $logged > 0
            ? "Incomplete logs cleared — {$logged} step(s) logged. Pull Latest Report & Record Deletions were left untouched."
            : 'Nothing to clear — no client had a missing weekly step. (Closeout steps are never auto-logged.)');
    }

    private function listView(Request $request, string $bucket)
    {
        $clientId = session('selected_client_id');

        $query = EndUser::forClient($clientId)
            ->notHeld()
            ->when($bucket === 'clients', fn ($q) => $q->done(), fn ($q) => $q->inProgress())
            // progress % is derived from the step log — eager load it so the
            // accessor doesn't fire a query per row; client carries the round
            // cycle length the date accessors read.
            ->with(['client', 'processSteps:id,end_user_id,round,step_type'])
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

    /** Add Client — full page form (replaces the old modal). */
    public function create()
    {
        $selectedClient = Client::forAdmin(Auth::guard('admin')->user()->dataOwnerId())
            ->findOrFail(session('selected_client_id'));

        return view('admin.end-users.create', compact('selectedClient'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedPayload($request, true);
        $boId = session('selected_client_id');

        // Server-side guard against duplicate clients (mirrors the live check on
        // the Add Client form). Scoped to this business owner.
        if ($this->emailExistsForBO($boId, $data['email'])) {
            return back()->withInput()
                ->withErrors(['email' => 'A client with this email already exists for this business owner.']);
        }
        if (!empty($data['ssn']) && $this->ssnExistsForBO($boId, $data['ssn'])) {
            return back()->withInput()
                ->withErrors(['ssn' => 'A client with this SSN already exists for this business owner.']);
        }

        $data['client_id'] = $boId;
        $data['status'] = 'active';

        $endUser = EndUser::create($data);

        $files = $this->handleFileUploads($request, $endUser);
        if ($files) {
            $endUser->update($files);
        }

        $this->storeNegativeItems($request, $endUser);

        // No owner-facing message on a team add — we never message the BO.

        return redirect()->route('admin.end-users.show', $endUser)->with('confirm', 'Client added');
    }

    /**
     * Save the negative items entered on the Add Client form. Only for owners
     * with results tracking on; each item's opened_on is the client's start date
     * so the enrollment month is attributed correctly. Blank rows are ignored.
     */
    private function storeNegativeItems(Request $request, EndUser $endUser): void
    {
        if (!$endUser->client?->resultsTrackingEnabled()) {
            return;
        }

        $openedOn = $endUser->start_date
            ? \Illuminate\Support\Carbon::parse($endUser->start_date)->toDateString()
            : now()->toDateString();
        $adminId = Auth::guard('admin')->id();

        foreach ((array) $request->input('negative_items', []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $category = (string) ($row['category'] ?? 'negative_account');
            $category = array_key_exists($category, NegativeItem::CATEGORIES) ? $category : 'negative_account';
            $goal     = NegativeItem::goalForCategory($category, (string) ($row['goal'] ?? 'delete'));
            $detail   = NegativeItem::detailForCategory($category, (string) ($row['detail'] ?? ''));
            $bureau   = trim((string) ($row['bureau'] ?? ''));

            $endUser->negativeItems()->create([
                'name'                => mb_substr($name, 0, 255),
                'detail'              => $detail,
                'category'            => $category,
                'goal'                => $goal,
                'bureau'              => array_key_exists($bureau, NegativeItem::BUREAUS) ? $bureau : 'all',
                'status'              => 'reporting',
                'opened_on'           => $openedOn,
                'created_by_admin_id' => $adminId,
            ]);
        }
    }

    /** Mark a client as awaiting the owner's approval for their next round (SOP §2). */
    public function requestRoundApproval(Request $request, string $id)
    {
        $endUser = $this->resultsScopedEndUser($id);
        $round   = min(8, max(1, $endUser->current_round + 1));

        $endUser->update([
            'round_approval_status' => 'awaiting',
            'round_approval_round'  => $round,
            'round_approval_at'     => now(),
        ]);

        return back()->with('confirm', "Marked awaiting approval for Round {$round}.");
    }

    /** Record that the owner approved the next round. */
    public function approveRound(string $id)
    {
        $endUser = $this->resultsScopedEndUser($id);
        $endUser->update([
            'round_approval_status' => 'approved',
            'round_approval_at'     => now(),
        ]);

        return back()->with('confirm', 'Round approved — you can proceed.');
    }

    /** Clear an approval request/state. */
    public function clearRoundApproval(string $id)
    {
        $endUser = $this->resultsScopedEndUser($id);
        $endUser->update([
            'round_approval_status' => null,
            'round_approval_round'  => null,
            'round_approval_at'     => null,
        ]);

        return back()->with('confirm', 'Approval status cleared.');
    }

    /** An end user in this org whose owner has results tracking on, or 404. */
    private function resultsScopedEndUser(string $id): EndUser
    {
        $ownerId = Auth::guard('admin')->user()->dataOwnerId();

        return EndUser::whereKey($id)
            ->whereHas('client', fn ($q) => $q->where('admin_id', $ownerId)->where('results_tracking', true))
            ->firstOrFail();
    }

    /**
     * Live duplicate check for the Add Client modal — email or SSN. Scoped to
     * the currently-selected business owner. Returns {exists, name}.
     */
    public function checkDuplicate(Request $request)
    {
        $field = $request->query('field');
        $value = trim((string) $request->query('value', ''));
        $boId  = session('selected_client_id');

        if ($value === '' || !$boId || !in_array($field, ['email', 'ssn'], true)) {
            return response()->json(['exists' => false]);
        }

        $match = $field === 'email'
            ? $this->matchByEmail($boId, $value)
            : $this->matchBySsn($boId, $value);

        return response()->json([
            'exists' => (bool) $match,
            'name'   => $match?->full_name,
        ]);
    }

    private function matchByEmail($boId, string $email): ?EndUser
    {
        return EndUser::where('client_id', $boId)
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->first();
    }

    /** SSNs are compared by digits only, so 243-41-9724 matches 243419724. */
    private function matchBySsn($boId, string $ssn): ?EndUser
    {
        $digits = preg_replace('/\D+/', '', $ssn);
        if ($digits === '') {
            return null;
        }
        return EndUser::where('client_id', $boId)
            ->get(['id', 'first_name', 'last_name', 'suffix', 'ssn'])
            ->first(fn ($eu) => preg_replace('/\D+/', '', (string) $eu->ssn) === $digits);
    }

    private function emailExistsForBO($boId, ?string $email): bool
    {
        return $boId && $email ? (bool) $this->matchByEmail($boId, $email) : false;
    }

    private function ssnExistsForBO($boId, ?string $ssn): bool
    {
        return $boId && $ssn ? (bool) $this->matchBySsn($boId, $ssn) : false;
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
            'negativeItems',
        ])->findOrFail($id);

        // Per-client audit timeline — super admin only.
        $activity = Auth::guard('admin')->user()->isSuper()
            ? $this->buildClientTimeline($endUser)
            : null;

        return view('admin.end-users.show', compact('endUser', 'activity'));
    }

    /**
     * Merge every tracked action on this client — moves/holds/profile edits
     * (client_events), process steps, comments, result items and document
     * uploads — into one timeline, newest first, each with the VA + timestamp.
     */
    private function buildClientTimeline(EndUser $eu): array
    {
        $eu->loadMissing(['clientEvents.admin', 'processSteps.createdBy', 'notes.createdBy', 'negativeItems.createdBy', 'documents.uploadedBy']);
        $t = [];

        foreach ($eu->clientEvents as $e) {
            $t[] = ['at' => $e->created_at, 'who' => $e->admin?->full_name ?? 'System', 'kind' => $e->event, 'text' => $e->description];
        }
        foreach ($eu->processSteps as $s) {
            $t[] = ['at' => $s->created_at, 'who' => $s->createdBy?->full_name ?? 'System', 'kind' => 'step',
                    'text' => 'Logged step: ' . ($s->step_type_label ?? $s->step_type) . " (Round {$s->round}, Week {$s->week})"];
        }
        foreach ($eu->notes as $n) {
            $t[] = ['at' => $n->created_at, 'who' => $n->createdBy?->full_name ?? 'System', 'kind' => 'comment',
                    'text' => 'Comment: ' . \Illuminate\Support\Str::limit($n->note_text, 90)];
        }
        foreach ($eu->negativeItems as $it) {
            $t[] = ['at' => $it->created_at, 'who' => $it->createdBy?->full_name ?? 'System', 'kind' => 'result',
                    'text' => 'Added result item: ' . $it->name . ' (' . $it->bureauLabel() . ')'];
        }
        foreach ($eu->documents as $d) {
            $t[] = ['at' => $d->created_at, 'who' => $d->uploadedBy?->full_name ?? 'System', 'kind' => 'doc',
                    'text' => 'Uploaded document: ' . $d->file_name];
        }

        usort($t, fn ($a, $b) => $b['at'] <=> $a['at']);
        return $t;
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

        // Per-round CFPB credentials (cfpb_rounds[<round>][email|password]). A
        // blank password for a round keeps that round's existing one, mirroring
        // the universal CFPB field. Merged into the encrypted map.
        unset($data['cfpb_rounds']);
        if ($request->has('cfpb_rounds')) {
            $existing = $endUser->cfpb_round_credentials ?? [];
            $merged   = [];
            foreach ((array) $request->input('cfpb_rounds', []) as $round => $vals) {
                $r = (int) $round;
                if ($r < 1 || $r > 8) {
                    continue;
                }
                $key      = (string) $r;
                $email    = trim((string) ($vals['email'] ?? ''));
                $pwIn     = (string) ($vals['password'] ?? '');
                $password = $pwIn !== '' ? $pwIn : ($existing[$key]['password'] ?? null);

                if ($email !== '' || (string) $password !== '') {
                    $merged[$key] = ['email' => $email !== '' ? $email : null, 'password' => $password];
                }
            }
            $data['cfpb_round_credentials'] = $merged ?: null;
        }

        // Record when a CFPB login is entered or changed (universal or per-round)
        // and who did it — powers the CFPB Logins daily report. Detected from the
        // raw input because the fields are encrypted (isDirty() can't tell). A
        // password typed in, or a new/changed email, counts; an unchanged profile
        // save does not, so plain edits don't falsely flag CFPB work.
        $cfpbChanged = false;
        $newEmail = trim((string) $request->input('cfpb_email', ''));
        if ($newEmail !== '' && $newEmail !== trim((string) ($endUser->cfpb_email ?? ''))) {
            $cfpbChanged = true;
        }
        if (trim((string) $request->input('cfpb_password', '')) !== '') {
            $cfpbChanged = true;
        }
        if (! $cfpbChanged && $request->has('cfpb_rounds')) {
            $existingRounds = $endUser->cfpb_round_credentials ?? [];
            foreach ((array) $request->input('cfpb_rounds', []) as $round => $vals) {
                if (trim((string) ($vals['password'] ?? '')) !== '') { $cfpbChanged = true; break; }
                $rEmail   = trim((string) ($vals['email'] ?? ''));
                $oldEmail = trim((string) ($existingRounds[(string) (int) $round]['email'] ?? ''));
                if ($rEmail !== '' && $rEmail !== $oldEmail) { $cfpbChanged = true; break; }
            }
        }
        if ($cfpbChanged) {
            $data['cfpb_logged_at']          = now();
            $data['cfpb_logged_by_admin_id'] = Auth::guard('admin')->id();
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

        // Overview "Rounds & Schedule" editor: set which rounds are reached AND
        // each round's individual start date in one submit. 1st Round's date is
        // the client start_date; later rounds live in round_dates[label]. Dates
        // for rounds that are no longer selected are kept so history is not lost.
        if ($request->has('round_schedule_present')) {
            $newRounds = array_values(array_intersect(EndUser::ROUND_OPTIONS, (array) $request->input('rounds', [])));
            $data['rounds'] = $newRounds ?: null;

            $inputDates = (array) $request->input('round_start_dates', []);
            $dates = $endUser->round_dates ?? [];

            foreach (EndUser::ROUND_OPTIONS as $label) {
                $raw = trim((string) ($inputDates[$label] ?? ''));
                $iso = null;
                if ($raw !== '') {
                    try { $iso = \Carbon\Carbon::parse($raw)->toDateString(); } catch (\Throwable $e) { $iso = null; }
                }

                if ($label === '1st Round') {
                    if (in_array($label, $newRounds, true) && $iso) {
                        $data['start_date'] = $iso;
                    }
                    continue;
                }

                if (in_array($label, $newRounds, true)) {
                    if ($iso) {
                        $dates[$label] = $iso;              // explicit date wins
                    } elseif (empty($dates[$label])) {
                        $dates[$label] = now()->toDateString(); // reached but undated → stamp today
                    }
                }
                // Rounds left unchecked keep any existing date (history preserved).
            }

            $data['round_dates'] = $dates ?: null;
        }

        $files = $this->handleFileUploads($request, $endUser);
        $data = array_merge($data, $files);

        $endUser->update($data);

        // A full profile-form submission (has first_name) is a client edit worth
        // logging; inline list edits (status/dates only) are not.
        if ($request->has('first_name')) {
            \App\Models\ClientEvent::log($endUser, 'profile', 'Edited client profile');
        }

        // Fall back to the client's own page (never the site root) if the
        // referer is missing — some hosts strip it on multipart posts, and we
        // must not land the user on the homepage or picker after a save.
        return back(302, [], route('admin.end-users.show', $endUser->id))
            ->with('confirm', 'Client updated');
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

    /** New Client Errors still awaiting a fix (business owner hasn't resolved). */
    public function errors()
    {
        $endUsers = EndUser::forClient(session('selected_client_id'))
            ->notHeld()
            ->newError()
            ->orderByDesc('updated_at')
            ->get();

        return view($this->adminView('admin.end-users.errors'), compact('endUsers'));
    }

    /** New Client Errors the business owner has resolved — awaiting VA processing. */
    public function errorsResolvedNewClients()
    {
        $endUsers = EndUser::forClient(session('selected_client_id'))
            ->notHeld()
            ->newErrorResolvedByClient()
            ->orderByDesc('error_resolved_by_client_at')
            ->get();

        return view($this->adminView('admin.end-users.errors-resolved-new'), compact('endUsers'));
    }

    /** Round Errors — clients past round 1 pulled out with an import problem. */
    public function roundErrors()
    {
        $endUsers = EndUser::forClient(session('selected_client_id'))
            ->notHeld()
            ->with('client')          // round-cycle length for the date accessors
            ->roundErrorPending()
            ->orderByDesc('updated_at')
            ->get();

        return view($this->adminView('admin.end-users.round-errors'), compact('endUsers'));
    }

    /** Round Errors the business owner has resolved — awaiting VA processing. */
    public function errorsResolvedByClient()
    {
        $endUsers = EndUser::forClient(session('selected_client_id'))
            ->notHeld()
            ->roundErrorResolvedByClient()
            ->orderByDesc('error_resolved_by_client_at')
            ->get();

        return view($this->adminView('admin.end-users.errors-resolved'), compact('endUsers'));
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
            // Fresh error starts as pending (business owner hasn't resolved it).
            'error_resolved_by_client_at' => null,
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
            'error_resolved_by_client_at' => null,
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
    public function hold(Request $request, string $id)
    {
        $reason = trim((string) $request->input('reason', ''));
        $endUser = $this->scoped()->findOrFail($id);
        $endUser->update([
            'held_at'     => now(),
            'move_reason' => $reason !== '' ? $reason : null,
        ]);

        return redirect()->back()
            ->with('status', "{$endUser->full_name} placed on Hold/Pause.");
    }

    /** Resume a held client — drops them back into their normal bucket. */
    public function resume(string $id)
    {
        $endUser = $this->scoped()->findOrFail($id);
        $endUser->update(['held_at' => null, 'move_reason' => null]);

        return redirect()->back()
            ->with('status', "{$endUser->full_name} resumed.");
    }

    public function approveIntake(string $id)
    {
        $endUser = $this->scoped()->findOrFail($id);

        // Move to In Progress. The round clock does NOT start here — it starts
        // when the client is moved into the Clients list (see moveToDone).
        $endUser->update([
            'intake_status'               => 'approved',
            'intake_review_note'          => null,
            'move_reason'                 => null,
            'error_resolved_by_client_at' => null,
        ]);

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
            'intake_status'               => 'done',
            'intake_review_note'          => null,
            'move_reason'                 => null,
            'start_date'                  => now()->toDateString(),
            'error_resolved_by_client_at' => null,
        ]);

        return redirect()->back()
            ->with('status', "{$endUser->full_name} moved to Clients — round clock started.");
    }

    /** Move a client back into the New Clients (pending review) list, with a reason. */
    public function moveToNewClients(Request $request, string $id)
    {
        $reason = trim((string) $request->input('reason', ''));
        $endUser = $this->scoped()->findOrFail($id);
        $endUser->update([
            'intake_status'               => 'pending_review',
            'intake_review_note'          => null,
            'move_reason'                 => $reason !== '' ? $reason : null,
            'error_resolved_by_client_at' => null,
        ]);

        return redirect()->back()
            ->with('status', "{$endUser->full_name} moved to New Clients.");
    }

    /** Move a client into the Errors bucket with a VA-entered error note. */
    public function moveToErrors(Request $request, string $id)
    {
        $endUser = $this->scoped()->findOrFail($id);
        $note = trim((string) $request->input('note', ''));

        $endUser->update([
            'intake_status'               => 'error',
            'intake_review_note'          => $note !== '' ? $note : null,
            // Fresh error starts as pending (business owner hasn't resolved it).
            'error_resolved_by_client_at' => null,
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
            // Address is required at intake, but on an edit these may be blank.
            // Laravel's ConvertEmptyStringsToNull turns an empty box into null, so
            // WITHOUT nullable a blank address field fails "must be a string" and
            // the whole save is rejected (this was the CFPB-save bounce bug).
            'current_address'             => "$reqOrNullable|string|max:255",
            'city'                        => "$reqOrNullable|string|max:120",
            'state'                       => "$reqOrNullable|string|max:120",
            'zipcode'                     => "$reqOrNullable|string|max:20",
            // Exactly 9 digits, no dashes/spaces (the form strips non-digits).
            'ssn'                         => "$reqOrNullable|regex:/^\\d{9}$/",
            'credit_monitoring_name'      => "$reqOrNullable|string|max:100",
            'credit_monitoring_username'  => "$reqOrNullable|string|max:255",
            'credit_monitoring_password'  => "$reqOrNullable|string|max:255",
            'credit_monitoring_security_answer' => "$reqOrNullable|string|max:255",
            'cfpb_email'                  => 'nullable|email|max:255',
            'cfpb_password'               => 'nullable|string|max:255',
            // Per-round CFPB credentials (only from the edit modal).
            'cfpb_rounds'                 => 'sometimes|array',
            'cfpb_rounds.*.email'         => 'nullable|email|max:255',
            'cfpb_rounds.*.password'      => 'nullable|string|max:255',
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
