<?php

namespace App\Services\Ghl;

use App\Models\Client;
use App\Models\EndUser;
use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Pulls Credit Repair Onboarding submissions out of Benny's GoHighLevel
 * sub-account and turns them into EndUsers here.
 *
 * A pull, not a webhook, and run on demand from the Sync now button so clients
 * arrive when a VA is actually there to review them. A missed webhook is missed
 * forever; a pull that fails is simply run again. The only state it keeps is
 * end_users.ghl_submission_id — that column IS the record of what has already
 * been pulled, so running it twice costs nothing and can never duplicate.
 *
 * Scope: Benny only. Nothing here touches the existing intake path or any other
 * business owner.
 */
class GhlIntakeSync
{
    /** GHL custom-field ids on the Credit Repair Onboarding Form. */
    private const F_SSN            = 'Qnl17jrCJh22OHn0Bvmv';
    private const F_PROVIDER       = 'D1OM62RZsLqj8Arvnyr1';
    private const F_MON_USER       = '10DLBuyTpyO9eGeZmOjn';
    private const F_MON_PASS       = 'q2eg0G2wJ2YbV0B7e2NX';
    private const F_SECURITY       = 'yJbT4DkN1qLCgIQTWT4J';
    private const F_CFPB_USER      = '2GNdDEKehMpTrz6Wzgj7';
    private const F_CFPB_PASS      = '8ZqrULT8BkT1HcXHQfBi';
    private const F_SMS_CONSENT    = 'Yu8b3f9k5t5QEYjRf83T';
    private const F_TERMS          = 'terms_and_conditions';
    private const F_DOC_LICENSE    = 'VbH2tpIKyayB4wCDrUhW';
    private const F_DOC_ADDRESS    = 'FHv3p7wit90sTdeFQ73d';
    private const F_DOC_SSN_CARD   = 'wnpfqKWgJWi6MJzVeqBv';

    /** GHL upload field => end_users column. */
    private const DOCUMENTS = [
        self::F_DOC_LICENSE  => 'photo_id_path',
        self::F_DOC_ADDRESS  => 'proof_of_address_path',
        self::F_DOC_SSN_CARD => 'ssn_picture_path',
    ];

    private const MAX_DOC_BYTES = 10 * 1024 * 1024;

    private const ALLOWED_DOC_MIMES = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
    ];

    /** false = not looked up yet, null = looked and found nothing. */
    private string|null|false $dateFormat = false;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $locationId,
        private readonly string $surveyId,
        private readonly int $clientId,
        private readonly string $baseUrl = 'https://services.leadconnectorhq.com',
        private readonly string $apiVersion = '2021-07-28',
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            apiKey: (string) config('services.ghl.api_key'),
            locationId: (string) config('services.ghl.location_id'),
            surveyId: (string) config('services.ghl.credit_repair_survey_id'),
            clientId: (int) config('services.ghl.client_id'),
        );
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->locationId !== ''
            && $this->surveyId !== '' && $this->clientId > 0;
    }

    /**
     * @return array{imported:int, linked:int, skipped:int, failed:int, errors:array<string>}
     */
    public function run(bool $dryRun = false): array
    {
        $result = ['imported' => 0, 'linked' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];

        foreach ($this->fetchSubmissions() as $submission) {
            $submissionId = (string) ($submission['id'] ?? '');

            if ($submissionId === '' || ($submission['surveyId'] ?? null) !== $this->surveyId) {
                continue;
            }

            if (EndUser::withTrashed()->where('ghl_submission_id', $submissionId)->exists()) {
                $result['skipped']++;
                continue;
            }

            $answers  = $this->normaliseAnswers($submission['others'] ?? []);
            $existing = $this->findExisting($answers, $submission);

            if ($dryRun) {
                $result[$existing ? 'linked' : 'imported']++;
                continue;
            }

            try {
                if ($existing) {
                    $this->link($existing, $submission, $answers);
                    $result['linked']++;
                } else {
                    $this->import($submission, $answers);
                    $result['imported']++;
                }
            } catch (Throwable $e) {
                $result['failed']++;
                $result['errors'][] = "{$submissionId}: {$e->getMessage()}";
                Log::error('GHL intake sync failed for submission ' . $submissionId, [
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /* ---------------------------------------------------------------- fetch */

    /** @return array<int, array<string, mixed>> */
    private function fetchSubmissions(int $maxPages = 20): array
    {
        $all  = [];
        $page = 1;

        do {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Version'       => $this->apiVersion,
                'Accept'        => 'application/json',
            ])->timeout(30)->retry(2, 500)->get($this->baseUrl . '/surveys/submissions', [
                'locationId' => $this->locationId,
                'limit'      => 100,
                'page'       => $page,
            ]);

            $response->throw();

            $body = $response->json();
            $all  = array_merge($all, $body['submissions'] ?? []);

            $next = $body['meta']['nextPage'] ?? null;
            $page = is_numeric($next) ? (int) $next : null;
        } while ($page !== null && $page <= $maxPages);

        return $all;
    }

    /* --------------------------------------------------------------- import */

    /**
     * Somebody may already have keyed this person in by hand — that is exactly
     * what happened with the first client we synced, typed in four days after
     * they onboarded. Matching on email within Benny's own list means we adopt
     * that record instead of creating a second copy of a real person.
     */
    private function findExisting(array $answers, array $submission): ?EndUser
    {
        $email = strtolower($this->str($answers['email'] ?? ($submission['email'] ?? '')));

        if ($email === '') {
            return null;
        }

        return EndUser::where('client_id', $this->clientId)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orderBy('id')
            ->first();
    }

    /**
     * Attach the GHL identifiers to a record that already existed, and fill in
     * only the fields nobody has filled yet. Never overwrites the team's own
     * work — if they corrected a spelling or uploaded a better licence, that
     * stands. The point is to stop duplicates and recover the data the manual
     * entry missed, not to relitigate the record.
     */
    private function link(EndUser $existing, array $submission, array $answers): void
    {
        [$question, $answer] = $this->splitSecurity($answers[self::F_SECURITY] ?? '');

        $existing->from_ghl          = true;
        $existing->ghl_contact_id    = $submission['contactId'] ?? $existing->ghl_contact_id;
        $existing->ghl_submission_id = $submission['id'];
        $existing->ghl_synced_at     = now();

        $candidates = [
            // We know when they submitted in GHL; a hand-keyed record usually has
            // no submission date at all, which leaves the history column blank.
            'intake_submitted_at'                 => $this->parseSubmittedAt($submission['createdAt'] ?? null),
            'ghl_dob_raw'                         => $this->str($answers['date_of_birth'] ?? ''),
            'ghl_consent'                         => $this->consentRecord($submission, $answers),
            'phone'                               => $this->str($answers['phone'] ?? ''),
            'date_of_birth'                       => $this->parseDob($answers['date_of_birth'] ?? null),
            'ssn'                                 => preg_replace('/\D/', '', (string) ($answers[self::F_SSN] ?? '')),
            'current_address'                     => $this->str($answers['address'] ?? ''),
            'city'                                => $this->str($answers['city'] ?? ''),
            'state'                               => $this->str($answers['state'] ?? ''),
            'zipcode'                             => $this->str($answers['postal_code'] ?? ''),
            'credit_monitoring_name'              => $this->monitoringProvider($answers),
            'credit_monitoring_username'          => $this->str($answers[self::F_MON_USER] ?? ''),
            'credit_monitoring_password'          => $this->str($answers[self::F_MON_PASS] ?? ''),
            'credit_monitoring_security_question' => (string) $question,
            'credit_monitoring_security_answer'   => (string) $answer,
            'cfpb_email'                          => $this->str($answers[self::F_CFPB_USER] ?? ''),
            'cfpb_password'                       => $this->str($answers[self::F_CFPB_PASS] ?? ''),
        ];

        $filled = [];
        foreach ($candidates as $column => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            if (filled($existing->$column)) {
                continue;
            }
            $existing->$column = $value;
            $filled[] = $column;
        }

        $existing->save();

        // Only attaches documents to columns that are still empty.
        $this->attachDocuments($existing, $answers);

        Message::postSystem(
            $this->clientId,
            "GoHighLevel onboarding for {$existing->full_name} was matched to their existing record"
            . ($filled ? ' and filled in ' . count($filled) . ' missing field(s).' : '.')
        );
    }

    private function import(array $submission, array $answers): EndUser
    {
        [$question, $answer] = $this->splitSecurity($answers[self::F_SECURITY] ?? '');

        $submittedAt = $this->parseSubmittedAt($submission['createdAt'] ?? null);

        $endUser = EndUser::create([
            'client_id'  => $this->clientId,
            'from_ghl'   => true,

            'ghl_contact_id'    => $submission['contactId'] ?? null,
            'ghl_submission_id' => $submission['id'],
            'ghl_synced_at'     => now(),

            'first_name'      => $this->str($answers['first_name'] ?? ''),
            'last_name'       => $this->str($answers['last_name'] ?? ''),
            'suffix'          => 'None',
            'email'           => $this->str($answers['email'] ?? ($submission['email'] ?? '')),
            'phone'           => $this->str($answers['phone'] ?? ''),
            'date_of_birth'   => $this->parseDob($answers['date_of_birth'] ?? null),
            'ghl_dob_raw'     => $this->str($answers['date_of_birth'] ?? ''),
            'ghl_consent'     => $this->consentRecord($submission, $answers),
            'ssn'             => preg_replace('/\D/', '', (string) ($answers[self::F_SSN] ?? '')),

            'current_address' => $this->str($answers['address'] ?? ''),
            'city'            => $this->str($answers['city'] ?? ''),
            'state'           => $this->str($answers['state'] ?? ''),
            'zipcode'         => $this->str($answers['postal_code'] ?? ''),

            'credit_monitoring_name'              => $this->monitoringProvider($answers),
            'credit_monitoring_username'          => $this->str($answers[self::F_MON_USER] ?? ''),
            'credit_monitoring_password'          => $this->str($answers[self::F_MON_PASS] ?? ''),
            'credit_monitoring_security_question' => $question,
            'credit_monitoring_security_answer'   => $answer,

            'cfpb_email'    => $this->str($answers[self::F_CFPB_USER] ?? ''),
            'cfpb_password' => $this->str($answers[self::F_CFPB_PASS] ?? ''),

            'status'       => 'active',
            // The day the client actually onboarded, not the day this job ran.
            // They differ whenever we backfill, and start_date is what the team
            // reads as "how long has this person been with us".
            'start_date'   => ($submittedAt ?? now())->toDateString(),
            // Same rule as the hosted intake: no round is marked on arrival.
            // Round 1 begins when the team logs its first Week-1 step.
            'rounds'       => [],
            'intake_status' => 'pending_review',
            'intake_submitted_at' => $submittedAt,
            'intake_submitted_ip' => $this->str($answers['ip'] ?? ''),
        ]);

        $this->attachDocuments($endUser, $answers);

        Message::postSystem(
            $this->clientId,
            "New GoHighLevel onboarding from {$endUser->full_name} is pending review in GHL Clients."
        );

        return $endUser;
    }

    /**
     * The consent evidence, written the way somebody would want to read it back
     * months later: what they ticked, in their own words, and when and from where.
     */
    private function consentRecord(array $submission, array $answers): string
    {
        $lines = [];

        $submitted = $this->parseSubmittedAt($submission['createdAt'] ?? null);
        $lines[] = 'Submitted: ' . ($submitted?->format('j M Y, H:i') ?? 'unknown') . ' UTC';

        if ($timezone = $this->str($answers['Timezone'] ?? '')) {
            $lines[] = 'Client timezone: ' . $timezone;
        }
        if ($ip = $this->str($answers['ip'] ?? '')) {
            $lines[] = 'IP address: ' . $ip;
        }

        $lines[] = 'Submission id: ' . ($submission['id'] ?? '?');
        $lines[] = '';

        if ($terms = $this->str($answers[self::F_TERMS] ?? '')) {
            $lines[] = 'Terms accepted:';
            $lines[] = '  ' . $terms;
        }

        $consent = $answers[self::F_SMS_CONSENT] ?? null;
        $consent = is_array($consent) ? $consent : array_filter([$this->str($consent)]);

        if ($consent) {
            $lines[] = 'Consent given:';
            foreach ($consent as $item) {
                $lines[] = '  ' . trim((string) $item);
            }
        }

        if (!$terms && !$consent) {
            $lines[] = 'No consent boxes were recorded on this submission.';
        }

        return implode("
", $lines);
    }

    /* ------------------------------------------------------------ documents */

    private function attachDocuments(EndUser $endUser, array $answers): void
    {
        $paths = [];

        foreach (self::DOCUMENTS as $field => $column) {
            // Never replace a document that is already on the record — on a
            // linked record that file is the team's, and may be the better copy.
            if (filled($endUser->$column)) {
                continue;
            }

            $file = $this->firstFile($answers[$field] ?? null);
            if (!$file) {
                continue;
            }

            $binary = $this->download($file['url']);
            if ($binary === null) {
                continue;
            }

            // Trust the bytes, not the label GHL gave us.
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary) ?: '';
            if (!isset(self::ALLOWED_DOC_MIMES[$mime]) || strlen($binary) > self::MAX_DOC_BYTES) {
                Log::warning('GHL intake: rejected document', [
                    'end_user' => $endUser->id,
                    'field'    => $field,
                    'mime'     => $mime,
                    'bytes'    => strlen($binary),
                ]);
                continue;
            }

            $name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) $file['name']));
            if ($name === '' || !str_contains($name, '.')) {
                $name = $field . '.' . self::ALLOWED_DOC_MIMES[$mime];
            }

            // Private disk, exactly as the hosted intake does. Never web-readable.
            $path = "uploads/{$endUser->id}/identity/" . time() . '_' . $name;
            Storage::disk('private')->put($path, $binary);
            $paths[$column] = $path;
        }

        if ($paths) {
            $endUser->update($paths);
        }
    }

    private function download(string $url): ?string
    {
        // GHL serves these without auth, but send the token anyway so this keeps
        // working if they ever tighten it.
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Version'       => $this->apiVersion,
        ])->timeout(60)->retry(2, 500)->get($url);

        return $response->successful() ? $response->body() : null;
    }

    /**
     * GHL stores an upload as { "<uuid>": { meta: {...}, url, documentId } }.
     * Only the first file per field is used — none of these fields are multi-upload.
     *
     * @return array{url:string, name:string}|null
     */
    private function firstFile(mixed $value): ?array
    {
        $value = $this->decode($value);
        if (!is_array($value)) {
            return null;
        }

        foreach ($value as $entry) {
            if (is_array($entry) && !empty($entry['url'])) {
                return [
                    'url'  => (string) $entry['url'],
                    'name' => (string) ($entry['meta']['originalname'] ?? ''),
                ];
            }
        }

        return null;
    }

    /* --------------------------------------------------------------- values */

    /** GHL sometimes hands back JSON-encoded strings rather than objects. */
    private function normaliseAnswers(mixed $others): array
    {
        $others = $this->decode($others);

        return is_array($others) ? $others : [];
    }

    private function decode(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        return $value;
    }

    /**
     * The hosted intake forces the BO's configured provider because its form does
     * not ask. The GHL form does ask, and the answer is what the client actually
     * enrolled in — so that wins, and the BO setting is only a fallback for when
     * they left it blank.
     */
    private function monitoringProvider(array $answers): string
    {
        $fromForm = $this->str($answers[self::F_PROVIDER] ?? '');
        if ($fromForm !== '') {
            return $fromForm;
        }

        return (string) (Client::find($this->clientId)?->intake_monitoring_provider ?? '');
    }

    private function str(mixed $value): string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return trim((string) $value);
    }

    /**
     * Store the date the client actually picked, not a rearrangement of it.
     *
     * The value must never reach strtotime or Laravel's `date` rule: PHP reads a
     * dash-separated date as dd-mm-yyyy, so the form's 07-04-1985 would quietly
     * become 7 April instead of 4 July. A wrong date of birth invalidates a
     * dispute and nothing downstream would question it.
     *
     * The order tried starts with whatever the form itself declares, so changing
     * the date format in the GHL builder cannot silently break this.
     */
    private function parseDob(mixed $raw): ?string
    {
        $raw = $this->str($raw);
        if ($raw === '') {
            return null;
        }

        $formats = array_values(array_unique(array_filter([
            $this->formDateFormat(),
            'm-d-Y', 'm/d/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d',
        ])));

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $raw);
            } catch (Throwable) {
                // Carbon throws rather than returning false when the value does
                // not match, so each attempt has to be guarded or the first miss
                // would abort the whole import.
                continue;
            }

            // Round-trip check: rejects a value that merely coerced into shape.
            if ($date && $date->format($format) === $raw) {
                return $date->toDateString();
            }
        }

        Log::warning('GHL intake: unrecognised date of birth', ['value' => $raw, 'tried' => $formats]);

        return null;
    }

    /**
     * The date format the GHL form is configured with, e.g. "MM-DD-YYYY" becomes
     * "m-d-Y". Read once per run; a failure here is not fatal, it just falls back
     * to the standard list.
     */
    private function formDateFormat(): ?string
    {
        if ($this->dateFormat !== false) {
            return $this->dateFormat;
        }

        $this->dateFormat = null;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Version'       => $this->apiVersion,
                'Accept'        => 'application/json',
            ])->timeout(20)->get($this->baseUrl . '/surveys/' . $this->surveyId);

            foreach ($response->json('survey.formData.slides') ?? [] as $slide) {
                foreach ($slide['slideData'] ?? [] as $field) {
                    if (($field['tag'] ?? null) !== 'date_of_birth' || empty($field['format'])) {
                        continue;
                    }

                    $this->dateFormat = str_replace(
                        ['YYYY', 'MM', 'DD'],
                        ['Y', 'm', 'd'],
                        strtoupper((string) $field['format'])
                    );

                    return $this->dateFormat;
                }
            }
        } catch (Throwable $e) {
            Log::info('GHL intake: could not read the form date format, using defaults', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->dateFormat;
    }

    private function parseSubmittedAt(mixed $raw): ?Carbon
    {
        $raw = $this->str($raw);

        try {
            return $raw === '' ? null : Carbon::parse($raw);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The GHL form puts the security question and its answer in one box, as
     * "What is the name of my first pet? / Jack". Split on the last " / " so a
     * question containing a slash still works. If there is no separator the
     * whole string is kept as the answer rather than thrown away.
     *
     * @return array{0:?string, 1:?string}
     */
    private function splitSecurity(mixed $raw): array
    {
        $raw = $this->str($raw);
        if ($raw === '') {
            return [null, null];
        }

        // Preferred separator, matching the field label "Security Word / Answer".
        $position = strrpos($raw, ' / ');
        if ($position !== false) {
            return [trim(substr($raw, 0, $position)), trim(substr($raw, $position + 3))];
        }

        // Plenty of people type the question out instead: "What was my first pet? Jack".
        if (preg_match('/^(.*\?)\s*(.+)$/u', $raw, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        // No separator at all — keep the whole thing as the answer rather than
        // guessing, and let the reviewer see there is no question recorded.
        return [null, $raw];
    }
}
