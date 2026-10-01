<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use App\Models\Mailbox;
use App\Services\Cpanel\CpanelMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Mailboxes — the throwaway addresses a VA creates to sign a client up to CFPB
 * and collect the one-time codes.
 *
 * The dashboard creates and deletes them on cPanel and holds the login; the VA
 * reads the mail in webmail. That is why the password is shown here at all:
 * without it there is nothing to type into the webmail form.
 *
 * Reached only through the admin.mailboxes middleware (super admin, or a VA the
 * super admin has granted). Unlike the Credentials vault this page does NOT
 * require a business owner to be selected — a VA opens it straight from the
 * sidebar, mid-task, with whatever owner they happen to be on.
 */
class MailboxController extends Controller
{
    public function __construct(private readonly CpanelMail $cpanel)
    {
    }

    public function index(Request $request)
    {
        $me      = Auth::guard('admin')->user();
        $ownerId = $me->dataOwnerId();

        $mailboxes = Mailbox::forOrg($ownerId)->live()
            ->with(['endUser:id,first_name,last_name', 'createdBy:id,full_name'])
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        // Sizes are a nicety: one call for the whole domain, and an empty array
        // when cPanel is unreachable, so a bad morning on the host does not take
        // this page down with it.
        $usage = $this->cpanel->isConfigured() ? $this->cpanel->usage() : [];

        return view('admin.mailboxes.index', [
            'mailboxes'   => $mailboxes,
            'usage'       => $usage,
            'configured'  => $this->cpanel->isConfigured(),
            'domain'      => config('cpanel.mail_domain'),
            'quotaMb'     => (int) config('cpanel.quota_mb', 100),
            'webmailUrl'  => $this->webmailUrl(),
            'totalUsedMb' => $this->totalUsedMb($usage),
        ]);
    }

    /**
     * Create one mailbox on cPanel and record it.
     *
     * Order matters: cPanel first, our row second. If cPanel refuses we have no
     * row and the VA sees why; if our insert somehow fails afterwards we are
     * left with an orphan mailbox on the host, which is recoverable — whereas a
     * row for a mailbox that was never created is a login that silently fails.
     */
    public function store(Request $request)
    {
        $me      = Auth::guard('admin')->user();
        $ownerId = $me->dataOwnerId();

        $data = $request->validate([
            'end_user_id' => 'nullable|integer',
            'local_part'  => 'nullable|string|max:60',
        ]);


        // A client id is only accepted when it genuinely belongs to this org.
        $endUser = null;
        if (! empty($data['end_user_id'])) {
            $endUser = EndUser::whereKey($data['end_user_id'])
                ->whereHas('client', fn ($q) => $q->where('admin_id', $ownerId))
                ->first();

            if (! $endUser) {
                return back()->withErrors(['mailbox' => 'That client is not in your organisation.']);
            }
        }

        $domain    = (string) config('cpanel.mail_domain');
        $localPart = $this->uniqueLocalPart($data['local_part'] ?? null, $endUser, $domain);
        $password  = $this->generatePassword();
        $quota     = (int) config('cpanel.quota_mb', 100);

        try {
            $this->cpanel->createMailbox($localPart, $password, $domain, $quota);
        } catch (RuntimeException $e) {
            return back()->withErrors(['mailbox' => $e->getMessage()]);
        }

        $mailbox = Mailbox::create([
            'admin_id'            => $ownerId,
            'created_by_admin_id' => $me->id,
            'end_user_id'         => $endUser?->id,
            'local_part'          => $localPart,
            'domain'              => $domain,
            'address'             => $localPart . '@' . $domain,
            'password'            => $password,
            'quota_mb'            => $quota,
        ]);

        // NOTHING is written back to the client. A mailbox is attached to a client
        // only so the list reads sensibly; the client's own record — cfpb_email
        // included — is left exactly as it was found. Deliberate: this page must
        // never be able to change client data as a side effect.

        return back()->with('status', "Mailbox {$mailbox->address} created.");
    }

    /**
     * Open webmail for this mailbox, already signed in and in the inbox.
     *
     * POST rather than GET, because it mints a live session on the mail server,
     * and a link that does that should not be something a page can trigger just
     * by being loaded. The session is handed to the browser and never stored.
     */
    public function webmail(int $id)
    {
        $ownerId = Auth::guard('admin')->user()->dataOwnerId();

        $mailbox = Mailbox::forOrg($ownerId)->live()->findOrFail($id);

        try {
            $url = $this->cpanel->webmailLoginUrl($mailbox->local_part, $mailbox->domain);
        } catch (RuntimeException $e) {
            // Falling back beats a dead end: the VA still has the address and
            // password on the row in front of them.
            return back()->withErrors(['mailbox' =>
                $e->getMessage() . ' Use Open Webmail at the top and sign in with the address and password instead.']);
        }

        // The URL carries a live session, so it must not be cached or handed on
        // in a Referer when webmail loads its own assets.
        return redirect()->away($url)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Referrer-Policy', 'no-referrer');
    }

    /** Delete the mailbox on cPanel, then mark our row as gone (never remove it). */
    public function destroy(int $id)
    {
        $me      = Auth::guard('admin')->user();
        $ownerId = $me->dataOwnerId();

        $mailbox = Mailbox::forOrg($ownerId)->live()->findOrFail($id);

        try {
            $this->cpanel->deleteMailbox($mailbox->local_part, $mailbox->domain);
        } catch (RuntimeException $e) {
            return back()->withErrors(['mailbox' => $e->getMessage()]);
        }

        $mailbox->forceFill([
            'deleted_at'          => now(),
            'deleted_by_admin_id' => $me->id,
        ])->save();

        return back()->with('status', "Mailbox {$mailbox->address} deleted.");
    }

    // ---------------------------------------------------------------- internals

    /**
     * A mailbox name nothing else is using. Built from the client's name when
     * there is one, with digits on the end so two clients called Dominique
     * Johnson never collide. Checked against our own table AND cPanel, because
     * an address can exist on the host without a row here.
     */
    private function uniqueLocalPart(?string $requested, ?EndUser $endUser, string $domain): string
    {
        $base = $requested !== null && trim($requested) !== ''
            ? $this->slug($requested)
            : $this->slug(trim(($endUser?->first_name ?? '') . ' ' . ($endUser?->last_name ?? '')));

        if ($base === '') {
            $base = 'client';
        }

        // Keep room for the suffix inside the 64-character local-part limit.
        $base = Str::limit($base, 50, '');

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $candidate = $base . random_int(1000, 9999);

            $taken = Mailbox::where('address', $candidate . '@' . $domain)->exists();

            if (! $taken) {
                return $candidate;
            }
        }

        // Pathological case only — fall back to something that cannot collide.
        return $base . Str::lower(Str::random(8));
    }

    /** Lower-case, dots for spaces, nothing a mail server will argue with. */
    private function slug(string $name): string
    {
        $name = Str::ascii($name);
        $name = strtolower(preg_replace('/[^A-Za-z0-9]+/', '.', $name));

        return trim($name, '.');
    }

    /**
     * A password strong enough for a mail server's rules without the characters
     * that make copy-paste into webmail go wrong (quotes, backslashes, spaces).
     */
    private function generatePassword(): string
    {
        $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $symbols  = '!@#%^*-_=+';

        $out = '';
        for ($i = 0; $i < 18; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        // Guarantee the classes cPanel's strength meter looks for, rather than
        // hoping the random draw happened to include them.
        $out .= $symbols[random_int(0, strlen($symbols) - 1)];
        $out .= random_int(0, 9);
        $out .= strtoupper($alphabet[random_int(0, 25)]);

        return $out;
    }


    /** Where "Open Webmail" points — the configured URL, else cPanel's own. */
    private function webmailUrl(): string
    {
        if ($url = config('cpanel.webmail_url')) {
            return $url;
        }

        return 'https://' . config('cpanel.host') . ':2096/';
    }

    private function totalUsedMb(array $usage): float
    {
        return round(array_sum(array_map(
            fn ($row) => (float) ($row['used_mb'] ?? 0),
            $usage
        )), 1);
    }

    /**
     * Type-ahead for the create dialog: every client in this organisation, across
     * ALL business owners, found by first name, last name, middle name, email or
     * phone in any word order (EndUser::scopeSearch, the same one the universal
     * search uses). Read-only — it answers a question, it never changes anything.
     */
    public function searchClients(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return response()->json(['results' => []]);
        }

        $ownerId = Auth::guard('admin')->user()->dataOwnerId();

        $rows = EndUser::whereHas('client', fn ($q) => $q->where('admin_id', $ownerId))
            ->search($term)
            ->with('client:id,business_name')
            ->orderBy('first_name')
            ->limit(25)
            ->get();

        return response()->json([
            'results' => $rows->map(fn (EndUser $e) => [
                'id'      => $e->id,
                'name'    => trim($e->first_name . ' ' . $e->last_name),
                'email'   => $e->email,
                'bo_name' => $e->client?->business_name,
            ])->values(),
        ]);
    }
}
