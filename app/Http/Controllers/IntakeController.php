<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\EndUser;
use App\Models\Message;
use Illuminate\Http\Request;

class IntakeController extends Controller
{
    /* ---------------- Hosted form (BOs without an external form) ---------------- */

    public function show(string $token)
    {
        $client = Client::where('intake_token', $token)->where('intake_enabled', true)->firstOrFail();
        abort_if((bool) $client->intake_external_url, 404); // external-form BOs use their own site

        return view('intake.show', compact('client', 'token'));
    }

    public function store(Request $request, string $token)
    {
        $client = Client::where('intake_token', $token)->where('intake_enabled', true)->firstOrFail();
        abort_if((bool) $client->intake_external_url, 404);

        $data = $request->validate($this->rules($client));
        $this->createEndUser($client, $data, $request);

        return redirect()->route('intake.success', ['token' => $token]);
    }

    public function success(string $token)
    {
        $client = Client::where('intake_token', $token)->firstOrFail();

        return view('intake.success', compact('client'));
    }

    /* ---------------- Server-to-server API (external forms, key-auth) ---------------- */

    public function apiStore(Request $request)
    {
        $key = $request->header('X-Intake-Key') ?: $request->input('intake_key');
        $client = $key
            ? Client::where('intake_api_key', $key)->where('intake_enabled', true)->first()
            : null;

        if (!$client) {
            return response()->json(['ok' => false, 'message' => 'Invalid or missing intake key.'], 401);
        }

        // Documents normally arrive as multipart file parts. Some hosts run a WAF
        // that rejects any multipart upload before PHP even runs (HTTP 406), which
        // would make the endpoint unusable for those partners. So a document may
        // instead be sent as base64 in a plain JSON body — same validation, same
        // private storage. Multipart remains the default and is unchanged.
        [$base64Docs, $base64Errors] = $this->decodeBase64Documents($request);

        if ($base64Errors) {
            return response()->json(['ok' => false, 'errors' => $base64Errors], 422);
        }

        $rules = $this->rules($client);
        // A document supplied as base64 no longer needs a file part for that field.
        foreach (array_keys($base64Docs) as $field) {
            $rules[$field] = 'nullable';
        }

        $validator = validator($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'errors' => $validator->errors()], 422);
        }

        $endUser = $this->createEndUser($client, $validator->validated(), $request, $base64Docs);

        return response()->json(['ok' => true, 'id' => $endUser->id], 201);
    }

    /**
     * Pull any base64-encoded documents off the request.
     *
     * Accepts "<field>_base64" (e.g. drivers_license_base64), optionally with
     * "<field>_filename". Returns [decoded, errors]; decoded is
     * ['drivers_license' => ['contents' => binary, 'filename' => safe name], ...].
     * Applies the same size and type limits as the multipart path.
     */
    private function decodeBase64Documents(Request $request): array
    {
        $allowed = ['drivers_license', 'ssn_card', 'proof_of_address'];
        $maxBytes = 10 * 1024 * 1024;                       // 10 MB, matches max:10240
        $allowedMimes = [
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
        ];

        $decoded = [];
        $errors  = [];

        foreach ($allowed as $field) {
            $raw = $request->input($field . '_base64');
            if (!is_string($raw) || $raw === '') {
                continue;
            }

            // tolerate a data: URI prefix and whitespace/newlines
            if (str_contains($raw, ',') && str_starts_with(trim($raw), 'data:')) {
                $raw = substr($raw, strpos($raw, ',') + 1);
            }
            $binary = base64_decode(preg_replace('/\s+/', '', $raw), true);

            if ($binary === false || $binary === '') {
                $errors[$field . '_base64'] = ['The ' . $field . ' base64 payload could not be decoded.'];
                continue;
            }

            if (strlen($binary) > $maxBytes) {
                $errors[$field . '_base64'] = ['The ' . $field . ' may not be greater than 10 MB.'];
                continue;
            }

            // Trust the bytes, not the caller: sniff the real type.
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary) ?: '';
            if (!isset($allowedMimes[$mime])) {
                $errors[$field . '_base64'] = ['The ' . $field . ' must be a PDF, JPG, PNG or WEBP file.'];
                continue;
            }

            $given = (string) $request->input($field . '_filename', '');
            $name  = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($given));
            if ($name === '' || !str_contains($name, '.')) {
                $name = $field . '.' . $allowedMimes[$mime];
            }

            $decoded[$field] = ['contents' => $binary, 'filename' => $name];
        }

        return [$decoded, $errors];
    }

    /* ---------------- shared ---------------- */

    private function rules(Client $client): array
    {
        $fixedProvider = $client->intake_monitoring_provider;

        return [
            'first_name'                        => 'required|string|max:100',
            'middle_name'                       => 'nullable|string|max:100',
            'last_name'                         => 'required|string|max:100',
            'suffix'                            => 'nullable|in:None,Jr.,Sr.,I,II,III,IV,V',
            'email'                             => 'required|email|max:255',
            'ssn'                               => 'required|string|max:32',
            'date_of_birth'                     => 'required|date|before:today',
            'current_address'                   => 'required|string|max:255',
            'address_line2'                     => 'nullable|string|max:120',
            'city'                              => 'required|string|max:120',
            'state'                             => 'required|string|max:120',
            'zipcode'                           => 'required|string|max:20',
            'phone'                             => 'required|string|max:30',
            'credit_monitoring_name'            => ($fixedProvider ? 'nullable' : 'required') . '|string|max:100',
            'credit_monitoring_username'        => 'required|string|max:255',
            'credit_monitoring_password'        => 'required|string|max:255',
            'credit_monitoring_security_answer'   => 'nullable|string|max:255',
            'credit_monitoring_security_question' => 'nullable|string|max:255',
            'credit_monitoring_pin'               => 'nullable|digits:4',
            'drivers_license'                   => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'ssn_card'                          => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'proof_of_address'                  => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ];
    }

    private function createEndUser(Client $client, array $data, Request $request, array $base64Docs = []): EndUser
    {
        // Force the BO's fixed monitoring provider when one is configured.
        if ($client->intake_monitoring_provider) {
            $data['credit_monitoring_name'] = $client->intake_monitoring_provider;
        }

        $endUser = EndUser::create([
            'client_id'                         => $client->id,
            'first_name'                        => $data['first_name'],
            'middle_name'                       => $data['middle_name'] ?? null,
            'last_name'                         => $data['last_name'],
            'suffix'                            => $data['suffix'] ?? 'None',
            'email'                             => $data['email'],
            'phone'                             => $data['phone'],
            'date_of_birth'                     => $data['date_of_birth'],
            'ssn'                               => $data['ssn'],
            'current_address'                   => $data['current_address'],
            'address_line2'                     => $data['address_line2'] ?? null,
            'city'                              => $data['city'],
            'state'                             => $data['state'],
            'zipcode'                           => $data['zipcode'],
            'credit_monitoring_name'            => $data['credit_monitoring_name'] ?? null,
            'credit_monitoring_username'        => $data['credit_monitoring_username'],
            'credit_monitoring_password'        => $data['credit_monitoring_password'],
            'credit_monitoring_security_answer'   => $data['credit_monitoring_security_answer'] ?? null,
            'credit_monitoring_security_question' => $data['credit_monitoring_security_question'] ?? null,
            'credit_monitoring_pin'               => $data['credit_monitoring_pin'] ?? null,
            'status'                            => 'active',
            'start_date'                        => now()->toDateString(),
            // No round is marked on intake. The 1st round starts only when the
            // team logs its first Week-1 step — that's when day-counting begins.
            'rounds'                            => [],
            'intake_status'                     => 'pending_review',
            'intake_submitted_ip'               => $request->ip(),
            'intake_submitted_at'               => now(),
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
            } elseif (isset($base64Docs[$field])) {
                // Same destination and private disk as an uploaded file.
                $filename = time() . '_' . $base64Docs[$field]['filename'];
                $path = "uploads/{$endUser->id}/identity/{$filename}";
                \Illuminate\Support\Facades\Storage::disk('private')->put($path, $base64Docs[$field]['contents']);
                $paths[$column] = $path;
            }
        }
        if ($paths) {
            $endUser->update($paths);
        }

        Message::postSystem(
            $client->id,
            "New intake submission from {$endUser->full_name} is pending review in New Clients."
        );

        return $endUser;
    }
}
