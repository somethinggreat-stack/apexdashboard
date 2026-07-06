<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EndUserController extends Controller
{
    public function index(Request $request)
    {
        $clientId = Auth::guard('client')->id();

        $query = EndUser::forClient($clientId)
            // Intake submissions awaiting review live in "New Clients".
            ->where(fn ($q) => $q->whereNull('intake_status')->orWhere('intake_status', '!=', 'pending_review'))
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

        return view('client.end-users.index', compact('endUsers'));
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
            'ssn'                               => 'required|string|max:32',
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
            'rounds'                            => ['1st Round'],
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
            ->where('intake_status', 'pending_review')
            ->orderByDesc('intake_submitted_at')
            ->get();

        return view('client.end-users.new-clients', ['endUsers' => $endUsers, 'client' => $client]);
    }

    /** Clients pulled out with an error the VA needs to fix — view only for the BO. */
    public function errors()
    {
        $endUsers = EndUser::forClient(Auth::guard('client')->id())
            ->where('intake_status', 'error')
            ->orderByDesc('updated_at')
            ->get();

        return view('client.end-users.errors', compact('endUsers'));
    }

    public function create()
    {
        return view('client.end-users.create');
    }

}
