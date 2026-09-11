<?php

namespace App\Services\DisputeFox;

use App\Models\EndUser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Sends a client into DisputeFox.
 *
 * DisputeFox has no customer API. Their web form endpoint is the only way in,
 * so this reproduces exactly what a browser submitting that form would send —
 * same field names, same hidden ids, same encoding.
 *
 * Two consequences of that worth knowing before changing anything here:
 *
 *  - Documents are NOT multipart. The form's own JavaScript concatenates the
 *    file input's value with a base64 data URL separated by "~~~~" and posts it
 *    as an ordinary text field. Sending a real file part instead is ignored.
 *  - The response is a redirect URL, not JSON, and carries no client id. There
 *    is nothing of DisputeFox's to store, so whether a client has been sent is
 *    recorded on our side in end_users.disputefox_pushed_at.
 */
class DisputeFoxPush
{
    /**
     * @return array{ok:bool, message:string, response:string}
     */
    public function push(EndUser $endUser): array
    {
        if (!config('disputefox.enabled')) {
            return ['ok' => false, 'message' => 'DisputeFox push is disabled.', 'response' => ''];
        }

        [$payload, $notes] = $this->buildPayload($endUser);

        try {
            $response = Http::asForm()
                ->timeout(120)
                ->withHeaders(['Accept' => '*/*'])
                ->post(
                    config('disputefox.url') . '?method=' . urlencode((string) config('disputefox.method')),
                    $payload
                );
        } catch (Throwable $e) {
            Log::error('DisputeFox push failed', ['end_user' => $endUser->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Could not reach DisputeFox: ' . $e->getMessage(), 'response' => ''];
        }

        $body = trim((string) $response->body());

        if (!$response->successful()) {
            return [
                'ok'       => false,
                'message'  => "DisputeFox returned {$response->status()}.",
                'response' => mb_substr($body, 0, 500),
            ];
        }

        // Their own form treats this string as the failure case.
        if (stripos($body, 'Account is in-activated') !== false) {
            return [
                'ok'       => false,
                'message'  => 'DisputeFox says the account is inactive.',
                'response' => mb_substr($body, 0, 500),
            ];
        }

        // A rejected submission comes back as JSON, e.g.
        // {"ErrorMessage":"We didn't found any form...","WebFormPresent":false}
        if (str_contains($body, 'ErrorMessage')) {
            $decoded = json_decode($body, true);

            return [
                'ok'       => false,
                'message'  => 'DisputeFox rejected it: ' . ($decoded['ErrorMessage'] ?? mb_substr($body, 0, 200)),
                'response' => mb_substr($body, 0, 500),
            ];
        }

        // Success echoes the marker that was posted as redirect_url. Anything
        // else — an empty body included — is not proof the client was created,
        // so it is not reported as one.
        if (!str_contains($body, (string) config('disputefox.ack_url'))) {
            return [
                'ok'       => false,
                'message'  => $body === ''
                    ? 'DisputeFox returned nothing. Treat as not sent, and check there before retrying.'
                    : 'Unexpected reply from DisputeFox: ' . mb_substr($body, 0, 200),
                'response' => mb_substr($body, 0, 500),
            ];
        }

        $message = 'Sent to DisputeFox.';
        if ($notes) {
            $message .= ' ' . implode(' ', $notes);
        }

        return ['ok' => true, 'message' => $message, 'response' => mb_substr($body, 0, 500)];
    }

    /**
     * @return array{0:array<string,mixed>, 1:array<string>}
     */
    private function buildPayload(EndUser $endUser): array
    {
        $notes = [];

        $payload = array_merge(
            [
                'method' => config('disputefox.method'),
                // Echoed back on success — the only way to tell an accepted
                // submission from a silently discarded one.
                'redirect_url' => config('disputefox.ack_url'),
            ],
            config('disputefox.hidden'),
            [
                'firstName'             => (string) $endUser->first_name,
                'lastName'              => (string) $endUser->last_name,
                'email1'                => (string) $endUser->email,
                'mobilePhone1'          => (string) $endUser->phone,

                // Their form validates digits only, nine at most.
                'socialSecurityNumber1' => preg_replace('/\D/', '', (string) $endUser->ssn),

                // The field is <input type="date">, so it expects Y-m-d.
                'dateOfBirth'           => $endUser->date_of_birth?->format('Y-m-d') ?? '',

                'currentAddress'        => (string) $endUser->current_address,
                'city'                  => (string) $endUser->city,
                'state'                 => (string) $endUser->state,
                'zipCode1'              => (string) $endUser->zipcode,

                'monitoringAgency'      => $this->agencyValue($endUser->credit_monitoring_name),
                'monitoringUsername'    => (string) $endUser->credit_monitoring_username,
                'password'              => (string) $endUser->credit_monitoring_password,

                'textField1'            => $this->securityLine($endUser),
                'textField2'            => (string) $endUser->cfpb_email,
                'textField3'            => (string) $endUser->cfpb_password,

                // The form posts the checkbox as a boolean string, not its label.
                'checkbox1'             => 'true',
            ]
        );

        foreach (config('disputefox.documents') as $column => $field) {
            $encoded = $this->encodeDocument($endUser, $column, $notes);
            if ($encoded !== null) {
                $payload[$field] = $encoded;
            }
        }

        return [$payload, $notes];
    }

    /** "MyScoreIQ" means nothing to the select; it wants 8. */
    private function agencyValue(?string $name): string
    {
        if (blank($name)) {
            return '';
        }

        $key = strtolower(preg_replace('/[^a-z]/i', '', $name));

        return (string) (config('disputefox.monitoring_agencies')[$key] ?? '');
    }

    /** One field on their side, question and answer on ours. */
    private function securityLine(EndUser $endUser): string
    {
        $question = trim((string) $endUser->credit_monitoring_security_question);
        $answer   = trim((string) $endUser->credit_monitoring_security_answer);

        if ($question !== '' && $answer !== '') {
            return $question . ' / ' . $answer;
        }

        return $answer !== '' ? $answer : $question;
    }

    /**
     * Rebuilds what the browser would have sent: the file input's value, then
     * "~~~~", then a base64 data URL of the contents.
     *
     * @param array<string> $notes
     */
    private function encodeDocument(EndUser $endUser, string $column, array &$notes): ?string
    {
        $path = $endUser->$column;
        if (blank($path)) {
            return null;
        }

        $disk = Storage::disk('private');

        if (!$disk->exists($path)) {
            $notes[] = basename($path) . ' is missing from storage and was not sent.';

            return null;
        }

        $size = $disk->size($path);
        if ($size > (int) config('disputefox.max_document_bytes')) {
            $notes[] = basename($path) . ' is too large to send (' . round($size / 1048576, 1) . ' MB) and was skipped.';

            return null;
        }

        $contents = $disk->get($path);
        $mime     = $disk->mimeType($path) ?: 'application/octet-stream';
        $name     = basename($path);

        // The browser sends the fake path its file input reports; mirror it so
        // whatever DisputeFox does to strip the directory still works.
        return 'C:\\fakepath\\' . $name . '~~~~data:' . $mime . ';base64,' . base64_encode($contents);
    }
}
