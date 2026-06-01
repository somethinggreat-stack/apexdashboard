<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EndUserController extends Controller
{
    public function index(Request $request)
    {
        $clientId = session('selected_client_id');

        $query = EndUser::forClient($clientId)
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

        return view('admin.end-users.index', compact('endUsers'));
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

        return back()->with('status', 'Client updated.');
    }

    public function destroy(string $id)
    {
        // The EndUser deleting hook removes its documents and identity files.
        $endUser = $this->scoped()->findOrFail($id);
        $name = $endUser->full_name;
        $endUser->delete();

        return redirect()->route('admin.end-users.index')->with('status', "Client {$name} deleted.");
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
            'ssn'                         => "$reqOrNullable|string|max:32",
            'credit_monitoring_name'      => "$reqOrNullable|string|max:100",
            'credit_monitoring_username'  => "$reqOrNullable|string|max:255",
            'credit_monitoring_password'  => "$reqOrNullable|string|max:255",
            'credit_monitoring_security_answer' => "$reqOrNullable|string|max:255",
            'cfpb_email'                  => 'nullable|email|max:255',
            'cfpb_password'               => 'nullable|string|max:255',
            'start_date'                  => "$req|date",
            'status'                      => 'sometimes|in:active,paused,graduated,cancelled',
            'rounds'                      => 'nullable|array|max:5',
            'rounds.*'                    => 'in:' . implode(',', EndUser::ROUND_OPTIONS),
            'photo_id'                    => "$reqOrNullable|file|mimes:pdf,jpg,jpeg,png|max:10240",
            'proof_of_address'            => "$reqOrNullable|file|mimes:pdf,jpg,jpeg,png|max:10240",
            'ssn_picture'                 => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ];

        $data = $request->validate($rules);
        unset($data['photo_id'], $data['proof_of_address'], $data['ssn_picture']);
        return $data;
    }

    private function handleFileUploads(Request $request, EndUser $endUser): array
    {
        $out = [];

        foreach ([
            'photo_id' => 'photo_id_path',
            'proof_of_address' => 'proof_of_address_path',
            'ssn_picture' => 'ssn_picture_path',
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
