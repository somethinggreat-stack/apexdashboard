<?php

namespace App\Services\Ghl;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The only thing that talks to GoHighLevel's conversations for the number pool.
 *
 * Every call below was measured against the live sub-account rather than taken
 * from memory, because the obvious guesses are wrong:
 *
 *   - /conversations/search is a GET. POSTing to it answers 404
 *     "Cannot POST /conversations/search".
 *   - An inbound message DOES name the number it arrived on: `to` is our
 *     number and `from` is the sender. That is what makes a per-number lock
 *     possible at all.
 *   - DELETE /conversations/{id} exists (an unknown id answers 400
 *     CONVERSATIONS_CONVERSATION_NOT_FOUND, not "Cannot DELETE").
 *
 * The token carries far more than this feature needs, so there is no general
 * "call anything" method — each public method below names the one thing it does.
 */
class GhlNumbers
{
    public function isConfigured(): bool
    {
        return (bool) (config('ghl_numbers.token') && config('ghl_numbers.location_id'));
    }

    /**
     * The pool: every number on the sub-account that is allowed to collect codes.
     *
     * Two independent gates, and a number must pass BOTH — an explicit exclude
     * list and a name pattern. The sub-account's Default Number carries live
     * customer conversations, so it is kept out here, at the lowest level, where
     * no screen or future helper can route around it.
     *
     * @return array<int, array{phone: string, sid: ?string, label: ?string}>
     */
    public function poolNumbers(): array
    {
        $rows = $this->get('/phone-system/numbers', ['locationId' => $this->location()])['phoneNumbers'] ?? [];

        $out = [];

        foreach ($rows as $row) {
            $phone = (string) ($row['value'] ?? '');
            $label = (string) ($row['title'] ?? '');

            if ($phone === '' || ! $this->inPool($phone, $label)) {
                continue;
            }

            $out[] = ['phone' => $phone, 'sid' => $row['sid'] ?? null, 'label' => $label];
        }

        return $out;
    }

    /** Is this number allowed to be claimed, polled or deleted from? */
    public function inPool(string $phone, string $label): bool
    {
        if (in_array($phone, (array) config('ghl_numbers.excluded', []), true)) {
            return false;
        }

        $pattern = (string) config('ghl_numbers.name_pattern', '');

        return $pattern === '' || (bool) preg_match($pattern, $label);
    }

    /**
     * Inbound SMS that arrived on one of `$toNumbers` after `$since`.
     *
     * One search call for the whole location, then messages only for threads
     * that have moved since the claim — polling every number separately would
     * be eleven calls where one will do.
     *
     * @param  string[]  $toNumbers
     * @return array<int, array{to: string, from: ?string, body: string, message_id: ?string, conversation_id: string, received_at: Carbon}>
     */
    public function inboundSince(array $toNumbers, Carbon $since): array
    {
        if (! $toNumbers) {
            return [];
        }

        $wanted  = array_flip($toNumbers);
        $sinceMs = $since->getTimestampMs();
        $found   = [];

        $conversations = $this->get('/conversations/search', [
            'locationId' => $this->location(),
            'limit'      => 50,
        ])['conversations'] ?? [];

        foreach ($conversations as $conversation) {
            // lastMessageDate is epoch ms. A thread untouched since the claim
            // cannot be carrying this VA's code.
            if ((int) ($conversation['lastMessageDate'] ?? 0) < $sinceMs) {
                continue;
            }

            $conversationId = (string) ($conversation['id'] ?? '');
            if ($conversationId === '') {
                continue;
            }

            foreach ($this->messages($conversationId) as $message) {
                if (($message['direction'] ?? '') !== 'inbound') {
                    continue;
                }

                $to = (string) ($message['to'] ?? '');
                if (! isset($wanted[$to])) {
                    continue;
                }

                $receivedAt = $this->when($message['dateAdded'] ?? null);
                if (! $receivedAt || $receivedAt->lt($since)) {
                    continue;
                }

                $found[] = [
                    'to'              => $to,
                    'from'            => $message['from'] ?? null,
                    'body'            => (string) ($message['body'] ?? ''),
                    'message_id'      => $message['id'] ?? null,
                    'conversation_id' => $conversationId,
                    'received_at'     => $receivedAt,
                ];
            }
        }

        // Oldest first, so a caller taking the last one gets the newest.
        usort($found, fn ($a, $b) => $a['received_at']->getTimestamp() <=> $b['received_at']->getTimestamp());

        return $found;
    }

    /** Every message in a thread, newest first as GHL returns them. */
    public function messages(string $conversationId): array
    {
        $body = $this->get('/conversations/' . $conversationId . '/messages');

        return $body['messages']['messages'] ?? ($body['messages'] ?? []);
    }

    /**
     * Delete a thread — but only if it is unmistakably a code thread.
     *
     * These live beside real customer conversations: payment promises, dispute
     * questions, actual people. Deleting is irreversible and GHL removes the
     * WHOLE thread, so this refuses unless every message in it is inbound and
     * addressed to a pool number. One outbound message, one message to a number
     * that is not in the pool, and it is left alone.
     *
     * Returns the reason it refused, or null when the thread was deleted.
     */
    public function deleteConversationSafely(string $conversationId, array $poolNumbers): ?string
    {
        $messages = $this->messages($conversationId);

        if (! $messages) {
            return 'the thread could not be read, so it was left alone';
        }

        $pool = array_flip($poolNumbers);

        foreach ($messages as $message) {
            if (($message['direction'] ?? '') !== 'inbound') {
                return 'the thread has replies in it, so it was left alone';
            }

            if (! isset($pool[(string) ($message['to'] ?? '')])) {
                return 'the thread touches a number outside the pool, so it was left alone';
            }
        }

        $this->request('delete', '/conversations/' . $conversationId);

        return null;
    }

    // ---------------------------------------------------------------- internals

    private function get(string $path, array $query = []): array
    {
        $response = $this->request('get', $path, $query);

        return is_array($response) ? $response : [];
    }

    /**
     * One GHL call. PRIVATE on purpose — this token carries every scope on the
     * sub-account, so a general-purpose proxy would make one bad request as
     * large as the whole account.
     */
    private function request(string $method, string $path, array $query = []): mixed
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('The number pool is not configured on this server yet.');
        }

        $url = rtrim((string) config('ghl_numbers.base_url'), '/') . $path;

        try {
            $request = Http::withHeaders([
                    'Authorization' => 'Bearer ' . config('ghl_numbers.token'),
                    'Version'       => (string) config('ghl_numbers.api_version'),
                    'Accept'        => 'application/json',
                ])
                ->timeout((int) config('ghl_numbers.timeout', 20));

            $response = $method === 'get'
                ? $request->get($url, $query)
                : $request->{$method}($url, $query);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach GoHighLevel. Try again in a moment.');
        }

        if (! $response->successful()) {
            // The URL can be logged; the Authorization header never is.
            Log::warning('GHL ' . strtoupper($method) . ' ' . $path . ' failed', ['status' => $response->status()]);

            throw new RuntimeException('GoHighLevel refused the request (HTTP ' . $response->status() . ').');
        }

        return $response->json();
    }

    /** GHL sends ISO 8601 on messages and epoch ms on conversations. */
    private function when(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return is_numeric($value)
                ? Carbon::createFromTimestampMs((int) $value)
                : Carbon::parse((string) $value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function location(): string
    {
        return (string) config('ghl_numbers.location_id');
    }
}
