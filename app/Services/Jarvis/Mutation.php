<?php

namespace App\Services\Jarvis;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Everything that must be true of a JARVIS write, in one place.
 *
 * Writes are OFF by default. When on, every mutation runs through here and gets:
 * the kill switch, the assistant's own identity, idempotency, preview-by-rollback
 * and an audit line that can never be mistaken for a human clicking.
 */
class Mutation
{
    /**
     * Run a mutation.
     *
     * `?preview=true` runs **the real handler**, inside a transaction, and rolls it
     * back. Not a separate "what would happen" path — that would drift from the real
     * one within a month and show Umair a diff from the wrong universe. Same
     * validation, same authorisation, same model events, same failure. The only
     * difference is the commit.
     *
     * @param  callable  $work  receives nothing, returns the response payload
     * @param  callable|null  $snapshot  state to diff, called before and after
     */
    public function run(Request $request, string $endpoint, callable $work, ?callable $snapshot = null): array
    {
        if (! config('jarvis.writes_enabled')) {
            abort(503, 'JARVIS writes are disabled.');
        }

        $preview = $request->boolean('preview');
        $key = (string) $request->header('Idempotency-Key', '');

        if (! $preview && $key === '') {
            abort(422, 'Idempotency-Key header is required for every mutation.');
        }

        // A retry must not apply the change twice. The first result is returned as-is.
        if (! $preview) {
            $seen = DB::table('jarvis_requests')->where('idempotency_key', $key)->first();
            if ($seen) {
                return ['replayed' => true] + json_decode((string) $seen->response, true);
            }
        }

        $requestId = (string) Str::uuid();
        $this->actAsAssistant();

        $before = $snapshot ? $snapshot() : null;

        if ($preview) {
            // Roll back whatever the real handler did, and report what it would have been.
            $result = null;
            $after = null;
            try {
                DB::transaction(function () use ($work, $snapshot, &$result, &$after) {
                    $result = $work();
                    $after = $snapshot ? $snapshot() : null;
                    throw new PreviewRollback();
                });
            } catch (PreviewRollback $e) {
                // expected — the transaction is undone
            }

            $this->audit($endpoint, $requestId, null, $request, true);

            return [
                'preview' => true,
                'would_change' => $this->diff($before, $after),
                'result' => $result,
            ];
        }

        $result = DB::transaction($work);
        $after = $snapshot ? $snapshot() : null;

        $payload = ['ok' => true, 'request_id' => $requestId, 'changed' => $this->diff($before, $after), 'result' => $result];

        DB::table('jarvis_requests')->insert([
            'idempotency_key' => $key,
            'endpoint'        => $endpoint,
            'request_id'      => $requestId,
            'params'          => json_encode($request->except(['preview'])),
            'response'        => json_encode($payload),
            'status'          => 200,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->audit($endpoint, $requestId, $key, $request, false);

        return $payload;
    }

    /**
     * Sign in as the assistant's own account for the rest of this request.
     *
     * Required, not cosmetic: the controller actions being proxied scope themselves
     * with `Auth::guard('admin')->user()->dataOwnerId()`, and `EndUser::booted()`
     * stamps `round_selections.admin_id` from the same place. Without this a round
     * advanced by JARVIS would be credited to nobody.
     */
    public function actAsAssistant(): Admin
    {
        $admin = Admin::where('email', config('jarvis.actor_email'))->first();

        abort_if(! $admin, 503, 'The JARVIS actor account is missing — run migrations.');

        Auth::guard('admin')->setUser($admin);

        return $admin;
    }

    /** Only the keys that actually moved, so the approval card shows the change and not the record. */
    private function diff(?array $before, ?array $after): array
    {
        if ($before === null || $after === null) {
            return [];
        }

        $out = [];
        foreach ($after as $k => $v) {
            $was = $before[$k] ?? null;
            if ($was !== $v) {
                $out[$k] = ['from' => $was, 'to' => $v];
            }
        }

        return $out;
    }

    /** Actor is always `jarvis` — never indistinguishable from Umair clicking. */
    private function audit(string $endpoint, string $requestId, ?string $key, Request $request, bool $preview): void
    {
        Log::channel('jarvis')->info($preview ? 'jarvis preview' : 'jarvis write', [
            'actor'           => 'jarvis',
            'endpoint'        => $endpoint,
            'request_id'      => $requestId,
            'idempotency_key' => $key,
            'params'          => $request->except(['preview']),
            'ip'              => $request->ip(),
            'at'              => now()->utc()->toIso8601String(),
        ]);
    }
}

/** Thrown to unwind a preview. Never escapes Mutation::run(). */
class PreviewRollback extends \RuntimeException
{
}
