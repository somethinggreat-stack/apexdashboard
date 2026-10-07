<?php

namespace App\Services\Cpanel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The only thing in this application that talks to cPanel.
 *
 * A cPanel API token is NOT an email-only credential — it carries every
 * permission the cPanel user has. So this class exposes four operations and
 * nothing else: create a mailbox, delete a mailbox, read mailbox sizes, and
 * mint a one-click webmail session for a single mailbox. There
 * is no "call any UAPI function" method, and `call()` is private, because the
 * moment a generic proxy exists the blast radius of one bad request becomes
 * the whole hosting account.
 *
 * Addresses in config('cpanel.protected') are refused here as well as in the
 * controller: the deepest layer should not depend on a caller remembering.
 */
class CpanelMail
{
    public function __construct(
        private readonly ?string $host = null,
        private readonly ?string $user = null,
        private readonly ?string $token = null,
    ) {
    }

    /** Configured well enough to try? Pages check this before offering the buttons. */
    public function isConfigured(): bool
    {
        return (bool) ($this->host() && $this->user() && $this->token());
    }

    /**
     * Create a mailbox. Returns nothing on success and throws on failure, so a
     * caller can never mistake a refusal for a mailbox.
     */
    public function createMailbox(string $localPart, string $password, ?string $domain = null, ?int $quotaMb = null): void
    {
        $localPart = $this->guardLocalPart($localPart);
        $quotaMb ??= (int) config('cpanel.quota_mb', 100);

        // cPanel reads 0 as "unlimited". A mailbox with no ceiling is exactly what
        // the quota is here to prevent, so refuse rather than quietly send it.
        if ($quotaMb < 1) {
            throw new RuntimeException('A mailbox quota must be at least 1 MB.');
        }

        $this->call('Email/add_pop', [
            'email'    => $localPart,
            'password' => $password,
            'quota'    => $quotaMb,
            'domain'   => $domain ?: $this->domain(),
        ]);
    }

    /** Delete a mailbox and everything in it. There is no undo on cPanel's side. */
    public function deleteMailbox(string $localPart, ?string $domain = null): void
    {
        $localPart = $this->guardLocalPart($localPart);

        $this->call('Email/delete_pop', [
            'email'  => $localPart,
            'domain' => $domain ?: $this->domain(),
        ]);
    }

    /**
     * A one-click sign-in URL that lands in this mailbox's Roundcube inbox.
     *
     * Every part of this was measured against the live server, because the
     * obvious shapes all fail silently-ish:
     *
     *   - `login` must be the LOCAL PART with `domain` passed separately.
     *     The full address makes cPanel look for "name@domain@domain"; omitting
     *     the domain is refused with "Provide the domain argument".
     *   - The URL must carry the session's own /cpsess…/ security token in the
     *     PATH. Without it webmail answers 401 "Invalid Security Token" and
     *     shows a password form — signed in to nothing.
     *   - goto_uri lands in Roundcube itself rather than cPanel's app chooser.
     *
     * The session is single-use and short-lived. It is never stored or logged.
     *
     * @throws RuntimeException with a message safe to show a VA.
     */
    public function webmailLoginUrl(string $localPart, ?string $domain = null): string
    {
        $localPart = $this->guardLocalPart($localPart);

        $data = $this->call('Session/create_webmail_session_for_mail_user', [
            'login'   => $localPart,
            'domain'  => $domain ?: $this->domain(),
            'service' => 'webmaild',
        ]);

        $session = is_array($data) ? ($data['session'] ?? null) : null;
        $token   = is_array($data) ? ($data['token'] ?? null)   : null;

        if (! $session || ! $token) {
            throw new RuntimeException('The mail server did not return a webmail session.');
        }

        // The token goes into the path, so it is checked rather than trusted: it
        // may only be a /cpsess… segment. The host always comes from our own
        // config — the reply says which session, never which server.
        if (! preg_match('#^/cpsess\d+$#', $token)) {
            throw new RuntimeException('The mail server returned an unexpected session token.');
        }

        return sprintf(
            'https://%s:2096%s/login/?session=%s&goto_uri=%s',
            $this->host(),
            $token,
            rawurlencode($session),
            rawurlencode('/3rdparty/roundcube/index.php')
        );
    }

    /**
     * Disk used per mailbox, keyed by full address: ['x@y.com' => ['used_mb' => 3.1, 'quota_mb' => 100]].
     *
     * Returns an empty array when cPanel cannot be reached. Sizes are a nicety;
     * the page must still render when the host is having a bad morning.
     */
    public function usage(): array
    {
        try {
            $rows = $this->call('Email/list_pops_with_disk', ['domain' => $this->domain()]);
        } catch (RuntimeException $e) {
            Log::warning('cPanel mailbox usage unavailable: ' . $e->getMessage());

            return [];
        }

        $out = [];

        foreach ((array) $rows as $row) {
            $address = $row['email'] ?? null;
            if (! $address) {
                continue;
            }
            // cPanel reports megabytes as strings, and an unlimited quota as 0 or "unlimited".
            $quota = $row['diskquota'] ?? null;
            $out[strtolower($address)] = [
                'used_mb'  => is_numeric($row['diskused'] ?? null) ? (float) $row['diskused'] : null,
                'quota_mb' => is_numeric($quota) && (float) $quota > 0 ? (float) $quota : null,
            ];
        }

        return $out;
    }

    /** True when cPanel already has this address. */
    public function exists(string $localPart, ?string $domain = null): bool
    {
        $address = strtolower($localPart . '@' . ($domain ?: $this->domain()));

        return array_key_exists($address, $this->usage());
    }

    // ---------------------------------------------------------------- internals

    /**
     * One UAPI call. PRIVATE on purpose — see the class docblock. Every public
     * method above names the exact cPanel function it needs.
     *
     * @throws RuntimeException with a message safe to show a VA (never the token).
     */
    private function call(string $function, array $params): mixed
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Mailbox creation is not configured on this server yet.');
        }

        $url = sprintf('https://%s:%d/execute/%s', $this->host(), (int) config('cpanel.port', 2083), $function);

        try {
            $response = Http::withHeaders([
                    // cPanel's own scheme: "cpanel <user>:<token>".
                    'Authorization' => 'cpanel ' . $this->user() . ':' . $this->token(),
                ])
                ->timeout((int) config('cpanel.timeout', 20))
                ->asForm()
                ->get($url, $params);
        } catch (ConnectionException $e) {
            // The message can carry the URL but never the Authorization header.
            throw new RuntimeException('Could not reach the mail server. Try again in a moment.');
        }

        if (! $response->successful()) {
            Log::warning('cPanel ' . $function . ' failed', ['status' => $response->status()]);

            throw new RuntimeException('The mail server refused the request (HTTP ' . $response->status() . ').');
        }

        $body = $response->json();

        // When the server's cPanel license lapses, cPanel answers every API call
        // with a 200 HTML "License is expired" page instead of JSON. Say so, rather
        // than the generic refusal that reads like a problem with one mailbox.
        if (! is_array($body) && str_contains($response->body(), 'License is expired')) {
            Log::warning('cPanel ' . $function . ' failed: server cPanel license expired');

            throw new RuntimeException('The hosting server\'s cPanel license has expired, so mailboxes cannot be changed. Ask the hosting provider to renew it.');
        }

        // UAPI answers 200 with status:0 for an application-level refusal — a
        // duplicate address, a weak password, a full disk. Treating that as
        // success is how you end up recording a mailbox that does not exist.
        if (! is_array($body) || ($body['status'] ?? 0) != 1) {
            throw new RuntimeException($this->errorFrom($body));
        }

        return $body['data'] ?? null;
    }

    /** cPanel's own words where it gives any, so a VA sees the real reason. */
    private function errorFrom(mixed $body): string
    {
        $errors = is_array($body) ? ($body['errors'] ?? null) : null;

        if (is_array($errors) && $errors !== []) {
            return (string) reset($errors);
        }

        return is_string($errors) && $errors !== ''
            ? $errors
            : 'The mail server rejected the request.';
    }

    /**
     * A local part this class is willing to act on. Rejects anything that is not
     * a plain mailbox name, and refuses the protected business addresses outright.
     */
    private function guardLocalPart(string $localPart): string
    {
        $localPart = strtolower(trim($localPart));

        if (! preg_match('/^[a-z0-9]([a-z0-9._-]{0,62}[a-z0-9])?$/', $localPart)) {
            throw new RuntimeException('That mailbox name contains characters the mail server will not accept.');
        }

        if (in_array($localPart, array_map('strtolower', (array) config('cpanel.protected', [])), true)) {
            throw new RuntimeException("'{$localPart}' is a protected business address and cannot be touched from here.");
        }

        return $localPart;
    }

    private function host(): ?string
    {
        return $this->host ?: config('cpanel.host');
    }

    private function user(): ?string
    {
        return $this->user ?: config('cpanel.user');
    }

    private function token(): ?string
    {
        return $this->token ?: config('cpanel.token');
    }

    private function domain(): string
    {
        return (string) config('cpanel.mail_domain');
    }
}
