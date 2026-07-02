<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\EndUser;
use App\Models\Message;
use Illuminate\Http\Request;

class IntakeController extends Controller
{
    public function show(string $token)
    {
        $client = Client::where('intake_token', $token)->where('intake_enabled', true)->firstOrFail();

        return view('intake.show', compact('client', 'token'));
    }

    public function store(Request $request, string $token)
    {
        $client = Client::where('intake_token', $token)->where('intake_enabled', true)->firstOrFail();

        // When the BO has a fixed monitoring provider, it's set server-side and
        // the form doesn't ask for it.
        $fixedProvider = $client->intake_monitoring_provider;

        $data = $request->validate([
            'first_name'                 => 'required|string|max:100',
            'middle_name'                => 'nullable|string|max:100',
            'last_name'                  => 'required|string|max:100',
            'suffix'                     => 'nullable|in:None,Jr.,Sr.,I,II,III,IV,V',
            'email'                      => 'required|email|max:255',
            'ssn'                        => 'required|string|max:32',
            'date_of_birth'              => 'required|date|before:today',
            'current_address'            => 'required|string|max:255',
            'address_line2'              => 'nullable|string|max:120',
            'city'                       => 'required|string|max:120',
            'state'                      => 'required|string|max:120',
            'zipcode'                    => 'required|string|max:20',
            'phone'                      => 'required|string|max:30',
            'credit_monitoring_name'     => ($fixedProvider ? 'nullable' : 'required') . '|string|max:100',
            'credit_monitoring_username' => 'required|string|max:255',
            'credit_monitoring_password' => 'required|string|max:255',
            'credit_monitoring_security_answer' => 'nullable|string|max:255',
            'drivers_license'            => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'ssn_card'                   => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'proof_of_address'           => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        if ($fixedProvider) {
            $data['credit_monitoring_name'] = $fixedProvider;
        }

        $endUser = EndUser::create([
            'client_id'                  => $client->id,
            'first_name'                 => $data['first_name'],
            'middle_name'                => $data['middle_name'] ?? null,
            'last_name'                  => $data['last_name'],
            'suffix'                     => $data['suffix'] ?? 'None',
            'email'                      => $data['email'],
            'phone'                      => $data['phone'],
            'date_of_birth'              => $data['date_of_birth'],
            'ssn'                        => $data['ssn'],
            'current_address'            => $data['current_address'],
            'address_line2'              => $data['address_line2'] ?? null,
            'city'                       => $data['city'],
            'state'                      => $data['state'],
            'zipcode'                    => $data['zipcode'],
            'credit_monitoring_name'     => $data['credit_monitoring_name'],
            'credit_monitoring_username' => $data['credit_monitoring_username'],
            'credit_monitoring_password' => $data['credit_monitoring_password'],
            'credit_monitoring_security_answer' => $data['credit_monitoring_security_answer'] ?? null,
            'status'                     => 'active',
            'start_date'                 => now()->toDateString(),
            'rounds'                     => ['1st Round'],
            'intake_status'              => 'pending_review',
            'intake_submitted_ip'        => $request->ip(),
            'intake_submitted_at'        => now(),
        ]);

        // Identity uploads go to the PRIVATE disk (never web-accessible).
        $columns = [
            'drivers_license'  => 'photo_id_path',
            'ssn_card'         => 'ssn_picture_path',
            'proof_of_address' => 'proof_of_address_path',
        ];
        $paths = [];
        foreach ($columns as $field => $column) {
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
            $client->id,
            "New intake submission from {$endUser->full_name} is pending review in New Clients."
        );

        return redirect()->route('intake.success', ['token' => $token]);
    }

    public function success(string $token)
    {
        $client = Client::where('intake_token', $token)->where('intake_enabled', true)->firstOrFail();

        return view('intake.success', compact('client'));
    }
}
