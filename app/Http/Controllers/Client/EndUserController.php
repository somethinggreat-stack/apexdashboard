<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EndUserController extends Controller
{
    /** "In Progress" — verified clients whose 1st round isn't done yet. */
    public function index(Request $request)
    {
        return $this->listView($request, 'in_progress');
    }

    /** "Done Clients" — the main list; all rounds after the 1st are worked here. */
    public function doneClients(Request $request)
    {
        return $this->listView($request, 'clients');
    }

    private function listView(Request $request, string $bucket)
    {
        $clientId = Auth::guard('client')->id();

        $query = EndUser::forClient($clientId)
            ->notHeld()   // Hold/Pause clients live in their own section
            ->noCustomList()   // clients tagged into a custom list show only there
            ->with(['client', 'processSteps:id,end_user_id,round,week,step_type,step_date'])   // cycle length + per-round progress for the accessors
            // New Clients (pending_review) and Errors live in their own sections.
            ->when($bucket === 'clients', fn ($q) => $q->done(), fn ($q) => $q->inProgress())
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

        if ($request->filled("search")) {
            $query->search($request->search);
        }

        $endUsers = $query->orderBy('start_date', 'asc')->orderBy('first_name')->get()
            ->sort(function ($a, $b) {
                $byIncomplete = ($b->is_incomplete ? 1 : 0) <=> ($a->is_incomplete ? 1 : 0);
                if ($byIncomplete !== 0) return $byIncomplete;
                return $a->start_date <=> $b->start_date;
            })
            ->values();

        return view('client.end-users.index', compact('endUsers', 'bucket'));
    }

    public function show(string $id)
    {
        $endUser = EndUser::forClient(Auth::guard('client')->id())
            ->with([
                'client',
                'processSteps.documents',
                'processSteps.createdBy',
                'documents',
                'scoreHistory',
                'notes.createdBy',
            ])
            ->findOrFail($id);

        return view('client.end-users.show', compact('endUser'));
    }

    public function statusReport(string $id)
    {
        $endUser = EndUser::forClient(Auth::guard('client')->id())
            ->with(['client', 'processSteps.createdBy', 'scoreHistory'])
            ->findOrFail($id);

        return view('client.end-users.status-report', compact('endUser'));
    }

    /**
     * Live duplicate check (email / SSN) for the BO's add-client form. Scoped to
     * this business owner's own clients. Returns {exists, name}.
     */
    public function checkDuplicate(Request $request)
    {
        $field    = $request->query('field');
        $value    = trim((string) $request->query('value', ''));
        $clientId = Auth::guard('client')->id();

        if ($value === '' || !in_array($field, ['email', 'ssn'], true)) {
            return response()->json(['exists' => false]);
        }

        $match = $field === 'email'
            ? $this->matchByEmail($clientId, $value)
            : $this->matchBySsn($clientId, $value);

        return response()->json([
            'exists' => (bool) $match,
            'name'   => $match?->full_name,
        ]);
    }

    private function matchByEmail($clientId, string $email): ?EndUser
    {
        return EndUser::where('client_id', $clientId)
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->first();
    }

    /** SSNs are compared by digits only, so 243-41-9724 matches 243419724. */
    private function matchBySsn($clientId, string $ssn): ?EndUser
    {
        $digits = preg_replace('/\D+/', '', $ssn);
        if ($digits === '') {
            return null;
        }
        return EndUser::where('client_id', $clientId)
            ->get(['id', 'first_name', 'last_name', 'suffix', 'ssn'])
            ->first(fn ($eu) => preg_replace('/\D+/', '', (string) $eu->ssn) === $digits);
    }

    private function emailExistsForBO($clientId, ?string $email): bool
    {
        return $clientId && $email ? (bool) $this->matchByEmail($clientId, $email) : false;
    }

    private function ssnExistsForBO($clientId, ?string $ssn): bool
    {
        return $clientId && $ssn ? (bool) $this->matchBySsn($clientId, $ssn) : false;
    }

    public function store(Request $request)
    {
        $clientId = Auth::guard('client')->id();

        $data = $request->validate([
            'first_name'                        => 'required|string|max:100',
            'last_name'                         => 'required|string|max:100',
            'suffix'                            => 'required|in:None,Jr.,Sr.,I,II,III,IV,V',
            'email'                             => 'required|email|max:255',
            'phone'                             => 'required|string|max:30',
            'date_of_birth'                     => 'required|date|before:today',
            'ssn'                               => 'required|regex:/^\d{9}$/',
            'current_address'                   => 'required|string|max:255',
            'city'                              => 'required|string|max:120',
            'state'                             => 'required|string|max:120',
            'zipcode'                           => 'required|string|max:20',
            'credit_monitoring_name'            => 'required|string|max:100',
            'credit_monitoring_username'        => 'required|string|max:255',
            'credit_monitoring_password'        => 'required|string|max:255',
            'credit_monitoring_security_answer' => 'nullable|string|max:255',
            'start_date'                        => 'required|date',
            'drivers_license'                   => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'ssn_card'                          => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'proof_of_address'                  => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        // Server-side guard against duplicate clients (mirrors the live check on
        // the add-client form). Scoped to this business owner's own clients.
        if ($this->emailExistsForBO($clientId, $data['email'])) {
            return back()->withInput()
                ->withErrors(['email' => 'A client with this email already exists in your account.']);
        }
        if (!empty($data['ssn']) && $this->ssnExistsForBO($clientId, $data['ssn'])) {
            return back()->withInput()
                ->withErrors(['ssn' => 'A client with this SSN already exists in your account.']);
        }

        // Business-owner submissions always land in New Clients for VA review.
        $endUser = EndUser::create([
            'client_id'                         => $clientId,
            'first_name'                        => $data['first_name'],
            'last_name'                         => $data['last_name'],
            'suffix'                            => $data['suffix'],
            'email'                             => $data['email'],
            'phone'                             => $data['phone'],
            'date_of_birth'                     => $data['date_of_birth'],
            'ssn'                               => $data['ssn'],
            'current_address'                   => $data['current_address'],
            'city'                              => $data['city'],
            'state'                             => $data['state'],
            'zipcode'                           => $data['zipcode'],
            'credit_monitoring_name'            => $data['credit_monitoring_name'],
            'credit_monitoring_username'        => $data['credit_monitoring_username'],
            'credit_monitoring_password'        => $data['credit_monitoring_password'],
            'credit_monitoring_security_answer' => $data['credit_monitoring_security_answer'] ?? null,
            'status'                            => 'active',
            'start_date'                        => $data['start_date'],
            // No round is marked on intake. The 1st round starts only when the
            // team logs its first Week-1 step — that's when day-counting begins.
            'rounds'                            => [],
            'intake_status'                     => 'pending_review',
            'intake_submitted_ip'               => $request->ip(),
            'intake_submitted_at'               => now(),
        ]);

        $paths = [];
        foreach (['drivers_license' => 'photo_id_path', 'ssn_card' => 'ssn_picture_path', 'proof_of_address' => 'proof_of_address_path'] as $field => $column) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $filename = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
                $paths[$column] = $file->storeAs("uploads/{$endUser->id}/identity", $filename, 'private');
            }
        }
        if ($paths) {
            $endUser->update($paths);
        }

        Message::postSystem(
            $clientId,
            "New client {$endUser->full_name} was submitted by the business owner — pending review in New Clients."
        );

        return redirect()->route('client.new-clients')
            ->with('status', 'Client submitted — it will appear in Clients once the team reviews it.');
    }

    public function newClients()
    {
        $client = Auth::guard('client')->user();
        abort_unless($client->intake_enabled, 404);

        $endUsers = EndUser::forClient($client->id)
            ->notHeld()
            ->noCustomList()
            ->where('intake_status', 'pending_review')
            ->orderByDesc('intake_submitted_at')
            ->get();

        return view('client.end-users.new-clients', ['endUsers' => $endUsers, 'client' => $client]);
    }

    /** New Client Errors still pending — the BO can resolve the login themselves. */
    public function errors()
    {
        $endUsers = EndUser::forClient(Auth::guard('client')->id())
            ->notHeld()
            ->noCustomList()
            ->newError()
            ->orderByDesc('updated_at')
            ->get();

        return view('client.end-users.errors', compact('endUsers'));
    }

    /** New Client Errors this owner has already resolved — read-only, awaiting the team. */
    public function errorsResolvedNew()
    {
        $endUsers = EndUser::forClient(Auth::guard('client')->id())
            ->notHeld()
            ->noCustomList()
            ->newErrorResolvedByClient()
            ->orderByDesc('error_resolved_by_client_at')
            ->get();

        return view('client.end-users.errors-resolved-new', compact('endUsers'));
    }

    /**
     * Business owner resolves a New Client Error by fixing the credit-monitoring
     * login — only their own client, only one still pending, and only the
     * credit-monitoring fields — then it moves to "Errors Resolved by You for New Clients".
     */
    public function resolveNewError(Request $request, string $id)
    {
        $endUser = EndUser::forClient(Auth::guard('client')->id())
            ->newError()
            ->findOrFail($id);

        $data = $request->validate([
            'credit_monitoring_name'              => 'nullable|string|max:100',
            'credit_monitoring_username'          => 'nullable|string|max:255',
            'credit_monitoring_password'          => 'nullable|string|max:255',
            'credit_monitoring_security_question' => 'nullable|string|max:255',
            'credit_monitoring_security_answer'   => 'nullable|string|max:255',
            'credit_monitoring_pin'               => 'nullable|digits:4',
        ]);

        // Every field is optional — blank means "keep whatever's already on file".
        $keepIfBlank = [
            'credit_monitoring_name', 'credit_monitoring_username', 'credit_monitoring_security_question',
            'credit_monitoring_password', 'credit_monitoring_security_answer', 'credit_monitoring_pin',
        ];
        foreach ($keepIfBlank as $field) {
            if (($data[$field] ?? null) === null || $data[$field] === '') {
                unset($data[$field]);
            }
        }

        $data['error_resolved_by_client_at'] = now();
        $endUser->update($data);

        return redirect()->route('client.errors-resolved-new')
            ->with('confirm', 'Error resolved — sent to our team');
    }

    /** Hold / Pause — the BO's clients the team has parked. View only for the BO. */
    public function holdList()
    {
        $endUsers = EndUser::forClient(Auth::guard('client')->id())
            ->onHold()
            ->noCustomList()
            ->orderByDesc('held_at')
            ->get();

        return view('client.end-users.hold', compact('endUsers'));
    }

    /** Round Errors — 2nd/3rd round problems the team is fixing. View only for the BO. */
    public function roundErrors()
    {
        $endUsers = EndUser::forClient(Auth::guard('client')->id())
            ->notHeld()
            ->noCustomList()
            ->roundErrorPending()
            ->orderByDesc('updated_at')
            ->get();

        return view('client.end-users.round-errors', compact('endUsers'));
    }

    /** Round Errors this owner has already resolved — read-only, awaiting the team. */
    public function errorsResolved()
    {
        $endUsers = EndUser::forClient(Auth::guard('client')->id())
            ->notHeld()
            ->noCustomList()
            ->roundErrorResolvedByClient()
            ->orderByDesc('error_resolved_by_client_at')
            ->get();

        return view('client.end-users.errors-resolved', compact('endUsers'));
    }

    /**
     * Business owner resolves a Round Error by fixing the credit-monitoring login.
     * Only their own client, only one still in a pending round error, and only the
     * credit-monitoring fields — then it moves to "Errors Resolved by You".
     */
    public function resolveRoundError(Request $request, string $id)
    {
        $endUser = EndUser::forClient(Auth::guard('client')->id())
            ->roundErrorPending()
            ->findOrFail($id);

        $data = $request->validate([
            'credit_monitoring_name'            => 'nullable|string|max:100',
            'credit_monitoring_username'        => 'nullable|string|max:255',
            'credit_monitoring_password'        => 'nullable|string|max:255',
            'credit_monitoring_security_question' => 'nullable|string|max:255',
            'credit_monitoring_security_answer' => 'nullable|string|max:255',
            'credit_monitoring_pin'             => 'nullable|digits:4',
        ]);

        // Every field is optional — blank means "keep whatever's already on file".
        $keepIfBlank = [
            'credit_monitoring_name', 'credit_monitoring_username', 'credit_monitoring_security_question',
            'credit_monitoring_password', 'credit_monitoring_security_answer', 'credit_monitoring_pin',
        ];
        foreach ($keepIfBlank as $field) {
            if (($data[$field] ?? null) === null || $data[$field] === '') {
                unset($data[$field]);
            }
        }

        $data['error_resolved_by_client_at'] = now();
        $endUser->update($data);

        return redirect()->route('client.errors-resolved')
            ->with('confirm', 'Error resolved — sent to our team');
    }

    public function create()
    {
        return view('client.end-users.create');
    }

    /**
     * A business owner's custom list (Jumbo / Mr Pierre / Tycoon). Only shown to
     * owners who have the feature turned on (Tycon Stan). Clients keep their
     * rounds and everything else exactly as in the normal lists — this is purely
     * a different grouping.
     */
    public function customList(Request $request, string $list)
    {
        $bo = Auth::guard('client')->user();
        abort_unless($bo->custom_lists_enabled && array_key_exists($list, EndUser::CUSTOM_LISTS), 404);

        $query = EndUser::forClient($bo->id)
            ->customList($list)
            ->withCount([
                'processSteps',
                'processSteps as week1_count' => fn ($q) => $q->where('week', 1),
                'processSteps as week2_count' => fn ($q) => $q->where('week', 2),
                'processSteps as week3_count' => fn ($q) => $q->where('week', 3),
                'processSteps as week4_count' => fn ($q) => $q->where('week', 4),
            ]);

        if ($request->filled("search")) {
            $query->search($request->search);
        }

        $endUsers = $query->orderBy('first_name')->get();

        return view('client.end-users.index', [
            'endUsers'  => $endUsers,
            'bucket'    => 'custom',
            'listKey'   => $list,
            'listLabel' => EndUser::CUSTOM_LISTS[$list],
        ]);
    }

    /**
     * Move one of this owner's clients into a custom list, or out of it. Posting
     * the client's current list toggles them back out (list = "none"). Owner-only,
     * gated to the feature flag; never affects rounds or work status.
     */
    public function moveToList(Request $request, string $id)
    {
        $bo = Auth::guard('client')->user();
        abort_unless($bo->custom_lists_enabled, 404);

        $data = $request->validate([
            'list' => ['required', Rule::in(array_merge(array_keys(EndUser::CUSTOM_LISTS), ['none']))],
        ]);

        $endUser = EndUser::forClient($bo->id)->findOrFail($id);
        $target  = $data['list'] === 'none' ? null : $data['list'];
        $endUser->update(['custom_list' => $target]);

        $msg = $target === null
            ? "{$endUser->full_name} removed from the list."
            : "{$endUser->full_name} moved to " . EndUser::CUSTOM_LISTS[$target] . '.';

        return back()->with('status', $msg);
    }

}
