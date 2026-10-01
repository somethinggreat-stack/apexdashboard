<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GhlNumber;
use App\Models\GhlOtp;
use App\Services\Ghl\GhlNumbers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Numbers — the shared pool a VA claims to collect a one-time code.
 *
 * A VA takes a number, uses it to sign a client up somewhere, and the code
 * lands here instead of in GoHighLevel. While they hold it nobody else can use
 * it, which is the whole point: two VAs on one number means two codes arriving
 * in one thread and neither of them knowing which is theirs.
 *
 * Reached only through the admin.numbers middleware (super admin, or a VA the
 * super admin has granted).
 */
class NumberController extends Controller
{
    public function __construct(private readonly GhlNumbers $ghl)
    {
    }

    public function index()
    {
        $this->syncPool();

        return view('admin.numbers.index', [
            'numbers'      => GhlNumber::active()->with('holder:id,full_name')->orderBy('label')->get(),
            'configured'   => $this->ghl->isConfigured(),
            'claimMinutes' => GhlNumber::claimMinutes(),
            'pollSeconds'  => (int) config('ghl_numbers.poll_seconds', 5),
            'me'           => Auth::guard('admin')->id(),
        ]);
    }

    /** Take a number, if it is actually free. */
    public function claim(int $id)
    {
        $me     = Auth::guard('admin')->user();
        $number = GhlNumber::active()->findOrFail($id);

        if (! $number->claim($me->id)) {
            return back()->withErrors(['number' =>
                'That number was taken a moment ago. Pick another one.']);
        }

        return back()->with('status', $number->label . ' is yours for ' . GhlNumber::claimMinutes() . ' minutes.');
    }

    /** Give it back without waiting for a code. */
    public function release(int $id)
    {
        $me     = Auth::guard('admin')->user();
        $number = GhlNumber::findOrFail($id);

        // A super admin can free a number somebody else is sitting on; a VA
        // can only let go of their own.
        if ($number->claimed_by_admin_id !== $me->id && ! $me->isSuper()) {
            return back()->withErrors(['number' => 'That number is held by someone else.']);
        }

        $number->release();

        return back()->with('status', $number->label . ' is back in the pool.');
    }

    /**
     * What has arrived for the numbers this VA is holding.
     *
     * Only the caller's own claims are polled: a VA holding nothing makes no
     * call to GoHighLevel at all, and a page left open on an idle desk costs
     * nothing.
     */
    public function poll()
    {
        $me = Auth::guard('admin')->user();

        $mine = GhlNumber::active()
            ->where('claimed_by_admin_id', $me->id)
            ->get()
            ->reject->claimExpired();

        if ($mine->isEmpty()) {
            return response()->json(['codes' => []]);
        }

        // Nothing older than the oldest live claim can belong to this VA.
        $since = $mine->min('claimed_at');

        try {
            $inbound = $this->ghl->inboundSince($mine->pluck('phone')->all(), $since);
        } catch (RuntimeException $e) {
            return response()->json(['codes' => [], 'error' => $e->getMessage()]);
        }

        $byPhone = $mine->keyBy('phone');
        $codes   = [];

        foreach ($inbound as $message) {
            $number = $byPhone[$message['to']] ?? null;

            // A message that predates THIS number's claim belongs to whatever
            // happened before it, not to the VA holding it now.
            if (! $number || $message['received_at']->lt($number->claimed_at)) {
                continue;
            }

            $otp = GhlOtp::firstOrCreate(
                ['message_id' => $message['message_id']],
                [
                    'ghl_number_id'       => $number->id,
                    'claimed_by_admin_id' => $me->id,
                    'from_number'         => $message['from'],
                    'code'                => GhlOtp::extractCode($message['body']),
                    'body'                => $message['body'],
                    'received_at'         => $message['received_at'],
                    'conversation_id'     => $message['conversation_id'],
                ]
            );

            if ($otp->copied_at) {
                continue;   // already dealt with
            }

            $codes[] = [
                'otp_id'    => $otp->id,
                'number_id' => $number->id,
                'phone'     => $number->phone,
                'label'     => $number->label,
                'code'      => $otp->code,
                'body'      => $otp->body,
                'from'      => $otp->from_number,
                'at'        => $otp->received_at?->toIso8601String(),
            ];
        }

        return response()->json(['codes' => $codes]);
    }

    /**
     * The VA has the code. Record it, free the number, and remove the thread
     * from GoHighLevel.
     *
     * The audit row is written FIRST and on purpose: once the conversation is
     * deleted it is the only evidence the code ever existed. A delete that
     * fails is reported on the row and leaves everything else correct — a lost
     * audit row would not be recoverable at all.
     */
    public function copied(Request $request, int $id)
    {
        $me  = Auth::guard('admin')->user();
        $otp = GhlOtp::whereKey($id)->firstOrFail();

        $number = GhlNumber::find($otp->ghl_number_id);

        if (! $number || ($number->claimed_by_admin_id !== $me->id && ! $me->isSuper())) {
            return response()->json(['ok' => false, 'error' => 'That code is not yours.'], 403);
        }

        $otp->forceFill(['copied_at' => now()])->save();

        $note = null;

        if (config('ghl_numbers.delete_after_copy') && $otp->conversation_id) {
            try {
                $note = $this->ghl->deleteConversationSafely(
                    $otp->conversation_id,
                    GhlNumber::active()->pluck('phone')->all()
                );
            } catch (RuntimeException $e) {
                $note = $e->getMessage();
            }

            $otp->forceFill([
                'deleted_from_ghl' => $note === null,
                'delete_note'      => $note,
            ])->save();
        }

        $number->release();

        return response()->json(['ok' => true, 'note' => $note]);
    }

    // ---------------------------------------------------------------- internals

    /**
     * Bring the pool in line with GoHighLevel.
     *
     * New pool numbers appear; a number that has left the pool is marked
     * inactive rather than deleted, so the codes that came to it keep their
     * history. Nothing here can introduce an excluded number — the service
     * filters before this ever sees a row.
     */
    private function syncPool(): void
    {
        if (! $this->ghl->isConfigured()) {
            return;
        }

        try {
            $live = $this->ghl->poolNumbers();
        } catch (RuntimeException $e) {
            return;   // the page still renders from what we already know
        }

        if (! $live) {
            return;
        }

        foreach ($live as $row) {
            GhlNumber::updateOrCreate(
                ['phone' => $row['phone']],
                ['ghl_sid' => $row['sid'], 'label' => $row['label'] ?: $row['phone'], 'active' => true]
            );
        }

        GhlNumber::whereNotIn('phone', array_column($live, 'phone'))->update(['active' => false]);
    }
}
