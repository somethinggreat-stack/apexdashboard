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

        $query = EndUser::forClient($clientId)->withCount('processSteps');

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

        $endUsers = $query->orderBy('first_name')->paginate(25)->withQueryString();

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

    public function store(Request $request)
    {
        $clientId = Auth::guard('client')->id();

        $data = $this->validatedPayload($request);
        $data['client_id']  = $clientId;
        $data['status']     = 'active';

        $endUser = EndUser::create($data);

        $files = $this->handleFileUploads($request, $endUser);
        if ($files) {
            $endUser->update($files);
        }

        Message::postSystem(
            $clientId,
            "New client {$endUser->full_name} has been added. Start working on it."
        );

        return redirect()->route('client.end-users.show', $endUser)
            ->with('status', 'Client added.');
    }

    private function validatedPayload(Request $request): array
    {
        $rules = [
            'first_name'                  => 'required|string|max:100',
            'last_name'                   => 'required|string|max:100',
            'suffix'                      => 'required|in:None,Jr.,Sr.,I,II,III,IV,V',
            'email'                       => 'required|email|max:255',
            'phone'                       => 'required|string|max:30',
            'date_of_birth'               => 'required|date|before:today',
            'ssn'                         => 'required|string|max:32',
            'credit_monitoring_name'      => 'required|string|max:100',
            'credit_monitoring_username'  => 'required|string|max:255',
            'credit_monitoring_password'  => 'required|string|max:255',
            'credit_monitoring_security_answer' => 'required|string|max:255',
            'cfpb_email'                  => 'nullable|email|max:255',
            'cfpb_password'               => 'nullable|string|max:255',
            'start_date'                  => 'required|date',
            'photo_id'                    => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'proof_of_address'            => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
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
            'photo_id'         => 'photo_id_path',
            'proof_of_address' => 'proof_of_address_path',
            'ssn_picture'      => 'ssn_picture_path',
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
