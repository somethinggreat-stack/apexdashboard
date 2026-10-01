<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsCode;
use App\Models\SmsNumber;
use App\Services\Sms\PlivoNumbers;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Phone Numbers — the shared pool a VA claims to collect a one-time code.
 *
 * A VA takes a number, uses it to sign a client up somewhere, and the code
 * lands here. While they hold it nobody else can use it, which is the whole
 * point: two VAs on one number means two codes arriving together and neither of
 * them knowing which is theirs.
 *
 * Nothing here polls the provider. Plivo pushes every inbound SMS to
 * SmsWebhookController the moment it arrives, so the page only ever asks our
 * own database — which is why it can ask every few seconds without cost.
 */
class NumberController extends Controller
{
    public function __construct(private readonly PlivoNumbers $plivo)
    {
    }

    public function index()
    {
        $this->syncPool();

        return view('admin.numbers.index', [
            'numbers'      => SmsNumber::active()->with('holder:id,full_name')->orderBy('label')->get(),
            'configured'   => $this->plivo->isConfigured(),
            'claimMinutes' => SmsNumber::claimMinutes(),
            'pollSeconds'  => (int) config('sms.poll_seconds', 3),
            'me'           => Auth::guard('admin')->id(),
        ]);
    }

    /** Take a number, if it is actually free. */
    public function claim(int $id)
    {
        $me     = Auth::guard('admin')->user();
        $number = SmsNumber::active()->findOrFail($id);

        if (! $number->claim($me->id)) {
            return back()->withErrors(['number' => 'That number was taken a moment ago. Pick another one.']);
        }

        return back()->with('status', $number->label . ' is yours for ' . SmsNumber::claimMinutes() . ' minutes.');
    }

    /** Give it back without waiting for a code. */
    public function release(int $id)
    {
        $me     = Auth::guard('admin')->user();
        $number = SmsNumber::findOrFail($id);

        // A super admin can free a number somebody else is sitting on; a VA can
        // only let go of their own.
        if ($number->claimed_by_admin_id !== $me->id && ! $me->isSuper()) {
            return back()->withErrors(['number' => 'That number is held by someone else.']);
        }

        $number->release();

        return back()->with('status', $number->label . ' is back in the pool.');
    }

    /**
     * Codes that have landed for the numbers this VA is holding.
     *
     * Reads our own tables only — the webhook has already written anything
     * there is to find. A VA holding nothing does one cheap query and stops.
     */
    public function poll()
    {
        $me = Auth::guard('admin')->user();

        $mine = SmsNumber::active()
            ->where('claimed_by_admin_id', $me->id)
            ->get()
            ->reject->claimExpired();

        if ($mine->isEmpty()) {
            return response()->json(['codes' => []]);
        }

        $codes = SmsCode::query()
            ->whereIn('sms_number_id', $mine->pluck('id'))
            ->whereNull('copied_at')
            ->with('number:id,phone,label')
            ->orderBy('received_at')
            ->get()
            // A code that arrived before THIS claim belongs to whatever happened
            // before it, not to the VA holding the number now.
            ->filter(fn (SmsCode $c) => $c->received_at
                && $c->received_at->gte($mine->firstWhere('id', $c->sms_number_id)->claimed_at));

        return response()->json([
            'codes' => $codes->map(fn (SmsCode $c) => [
                'otp_id'    => $c->id,
                'number_id' => $c->sms_number_id,
                'phone'     => $c->number?->phone,
                'label'     => $c->number?->label,
                'code'      => $c->code,
                'body'      => $c->body,
                'from'      => $c->from_number,
                'at'        => $c->received_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * The VA has the code. Record that, and free the number.
     *
     * There is nothing to delete anywhere: the message was delivered to this
     * application and nowhere else, so this row is the only copy of it.
     */
    public function copied(int $id)
    {
        $me  = Auth::guard('admin')->user();
        $code = SmsCode::whereKey($id)->firstOrFail();

        $number = SmsNumber::find($code->sms_number_id);

        if (! $number || ($number->claimed_by_admin_id !== $me->id && ! $me->isSuper())) {
            return response()->json(['ok' => false, 'error' => 'That code is not yours.'], 403);
        }

        $code->forceFill(['copied_at' => now(), 'claimed_by_admin_id' => $me->id])->save();

        $number->release();

        return response()->json(['ok' => true]);
    }

    // ---------------------------------------------------------------- internals

    /**
     * Bring the pool in line with the provider.
     *
     * New numbers appear; one that has gone is marked inactive rather than
     * deleted, so the codes that came to it keep their history.
     */
    private function syncPool(): void
    {
        if (! $this->plivo->isConfigured()) {
            return;
        }

        try {
            $live = $this->plivo->numbers();
        } catch (RuntimeException $e) {
            return;   // the page still renders from what we already know
        }

        if (! $live) {
            return;
        }

        foreach ($live as $row) {
            SmsNumber::updateOrCreate(
                ['phone' => $row['phone']],
                ['label' => $row['label'] ?: $row['phone'], 'active' => true]
            );
        }

        SmsNumber::whereNotIn('phone', array_column($live, 'phone'))->update(['active' => false]);
    }
}
