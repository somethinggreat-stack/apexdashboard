<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\EndUser;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class IntakeController extends Controller
{
    /**
     * Render the public intake form for a given BO token.
     */
    public function show(string $token)
    {
        $client = $this->resolveClient($token);

        return view('intake.show', compact('client'));
    }

    /**
     * Accept an intake submission against a given BO token.
     */
    public function store(Request $request, string $token)
    {
        $client = $this->resolveClient($token);

        $data = $request->validate([
            'first_name'                        => 'required|string|max:100',
            'middle_name'                       => 'nullable|string|max:100',
            'last_name'                         => 'required|string|max:100',
            'suffix'                            => 'required|in:None,Jr.,Sr.,I,II,III,IV,V',
            'email'                             => 'required|email|max:255',
            'phone'                             => 'required|string|max:30',
            'date_of_birth'                     => 'required|date|before:today',
            'ssn'                               => 'required|string|max:32',
            'credit_monitoring_name'            => 'required|string|max:100',
            'credit_monitoring_username'        => 'required|string|max:255',
            'credit_monitoring_password'        => 'required|string|max:255',
            'credit_monitoring_security_answer' => 'required|string|max:255',
            'collage'                           => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        // Create the EndUser first (without file paths). Encryption casts on
        // ssn / credit_monitoring_password / security_answer apply automatically.
        $endUser = EndUser::create([
            'client_id'                         => $client->id,
            'first_name'                        => $data['first_name'],
            'middle_name'                       => $data['middle_name'] ?? null,
            'last_name'                         => $data['last_name'],
            'suffix'                            => $data['suffix'],
            'email'                             => $data['email'],
            'phone'                             => $data['phone'],
            'date_of_birth'                     => $data['date_of_birth'],
            'ssn'                               => $data['ssn'],
            'credit_monitoring_name'            => $data['credit_monitoring_name'],
            'credit_monitoring_username'        => $data['credit_monitoring_username'],
            'credit_monitoring_password'        => $data['credit_monitoring_password'],
            'credit_monitoring_security_answer' => $data['credit_monitoring_security_answer'],
            'start_date'                        => now()->toDateString(),
            'status'                            => 'active',
            'intake_status'                     => 'pending_review',
            'intake_submitted_ip'               => $request->ip(),
            'intake_submitted_at'               => now(),
        ]);

        // Now move the uploaded files to the private disk under this user's id.
        $files = $this->storeUploads($request, $endUser);
        if ($files) {
            $endUser->update($files);
        }

        // Notify the admin: post a system message in the BO's conversation.
        Message::postSystem(
            $client->id,
            "🆕 NEW INTAKE SUBMISSION (please review): {$endUser->full_name} — {$endUser->email}. Submitted via public intake form at " . now()->format('M d, Y · g:i A') . "."
        );

        return redirect()->route('intake.success', $token);
    }

    public function success(string $token)
    {
        $client = $this->resolveClient($token);
        return view('intake.success', compact('client'));
    }

    /**
     * Look up an active Client by intake token, abort 404 otherwise.
     * Inactive business owners cannot accept submissions.
     */
    private function resolveClient(string $token): Client
    {
        abort_unless(strlen($token) >= 24, 404);

        $client = Client::where('intake_token', $token)
            ->where('status', 'active')
            ->first();

        abort_unless($client, 404);

        return $client;
    }

    private function storeUploads(Request $request, EndUser $endUser): array
    {
        $out = [];

        foreach ([
            'collage' => 'collage_path',
        ] as $field => $column) {
            if (!$request->hasFile($field)) continue;

            $file = $request->file($field);
            $filename = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            $path = $file->storeAs("uploads/{$endUser->id}/identity", $filename, 'private');
            $out[$column] = $path;
        }

        return $out;
    }
}
