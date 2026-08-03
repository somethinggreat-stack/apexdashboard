<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\EndUser;
use App\Models\Invoice;
use App\Models\PeriodHours;
use App\Models\TimeEntry;
use App\Models\TimePayout;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index()
    {
        $client = $this->scopedBO();
        $model  = $client->compensation_model ?: 'per_round';

        if ($model === 'hourly') {
            return view('admin.payments.index', [
                'client' => $client,
                'model'  => 'hourly',
                'data'   => $this->buildHourlyData($client),
            ]);
        }

        return view('admin.payments.index', [
            'client' => $client,
            'model'  => 'per_round',
            'data'   => $this->buildPerRoundData($client),
        ]);
    }

    public function updateConfig(Request $request)
    {
        $client = $this->scopedBO();

        $data = $request->validate([
            'compensation_model'  => 'required|in:per_round,hourly',
            'per_round_fee'       => 'nullable|numeric|min:0',
            'hourly_rate'         => 'nullable|numeric|min:0',
            'weekly_hours_target' => 'nullable|integer|min:0|max:168',
            'pay_cycle'           => 'nullable|in:biweekly,monthly',
            'pay_cycle_anchor'    => 'nullable|date',
        ]);

        if ($data['compensation_model'] === 'per_round') {
            $data['hourly_rate']         = null;
            $data['weekly_hours_target'] = null;
            $data['pay_cycle']           = null;
            $data['pay_cycle_anchor']    = null;
        } else {
            $data['per_round_fee'] = null;
            if (empty($data['pay_cycle_anchor'])) {
                $data['pay_cycle_anchor'] = now()->startOfWeek()->toDateString();
            }
            if (empty($data['pay_cycle'])) {
                $data['pay_cycle'] = 'biweekly';
            }
        }

        $client->update($data);

        return back()->with('status', 'Payment settings updated.');
    }

    /* =============== PER-ROUND =============== */

    public function storePayment(Request $request)
    {
        $client = $this->scopedBO();

        $endUserRule = Rule::exists('end_users', 'id')
            ->where(fn ($q) => $q->where('client_id', $client->id));

        $data = $request->validate([
            'end_user_id' => ['required', $endUserRule],
            'round'       => 'required|integer|between:1,8',
            'amount'      => 'nullable|numeric|min:0',
            'paid_at'     => 'nullable|date',
            'method'      => 'nullable|string|max:50',
            'notes'       => 'nullable|string|max:1000',
        ]);

        // Test/free: closes the round like a normal payment, but at $0 and flagged
        // so it never counts toward revenue or Chantal's commission.
        $isFree = $request->boolean('free');

        if ($isFree) {
            $data['amount'] = 0;
        } elseif (($data['amount'] ?? null) === null) {
            // Sensible default — mark paid in 1 click. Uses the client's effective
            // rate (their custom per-round fee if set, otherwise the BO default).
            $endUser = EndUser::with('client')->find($data['end_user_id']);
            $data['amount'] = $endUser ? $endUser->effectiveRoundFee((int) $data['round']) : (float) ($client->per_round_fee ?? 0);
        }

        $data['is_free'] = $isFree;
        $data['paid_at'] = $data['paid_at'] ?? now()->toDateString();
        $data['created_by_admin_id'] = Auth::guard('admin')->id();

        ClientPayment::updateOrCreate(
            ['end_user_id' => $data['end_user_id'], 'round' => $data['round']],
            $data
        );

        return back()->with('status', 'Marked paid.');
    }

    /**
     * Create a saved Invoice record from the BO's current unpaid items,
     * then redirect to the printable invoice page (which auto-prints).
     */
    public function generateInvoice()
    {
        $client = $this->scopedBO();

        if (($client->compensation_model ?? 'per_round') === 'hourly') {
            return $this->generateHourlyInvoice($client);
        }

        $data = $this->buildPerRoundData($client);

        // Complimentary rounds carry $0, so they never change the total — they're
        // included so the BO can see what was delivered free of charge.
        $items = array_merge($data['unpaidItems'], $data['freeItems']);

        if (empty($items)) {
            return back()->with('status', 'No unpaid items to invoice — everything is already paid.');
        }

        $invoice = Invoice::create([
            'client_id'           => $client->id,
            'invoice_number'      => $this->nextInvoiceNumber($client, now()),
            'invoice_date'        => now()->toDateString(),
            'items'               => $items,
            'total'               => $data['totalUnpaid'],
            'created_by_admin_id' => Auth::guard('admin')->id(),
        ]);

        return redirect()->route('admin.payments.invoice.show', $invoice);
    }

    /**
     * Build an hourly invoice for the current pay period — a single line for
     * the period's total hours (hours × rate).
     */
    private function generateHourlyInvoice(Client $client)
    {
        $data  = $this->buildHourlyData($client);
        $rate  = (float) $data['rate'];
        $start = $data['currentStart'];
        $end   = $data['currentEnd'];
        $hours = (float) $data['hoursThisPeriod'];

        if ($hours <= 0) {
            return back()->with('status', 'No hours in the current period to invoice.');
        }

        $items = [[
            'type'        => 'hourly',
            'date'        => $start->toDateString(),
            'label'       => $start->format('M j, Y') . ' – ' . $end->format('M j, Y'),
            'description' => 'Hourly work',
            'hours'       => $hours,
            'rate'        => $rate,
            'amount'      => round($hours * $rate, 2),
        ]];

        $total = round($hours * $rate, 2);

        $invoice = Invoice::create([
            'client_id'           => $client->id,
            'invoice_number'      => $this->nextInvoiceNumber($client, now()),
            'invoice_date'        => now()->toDateString(),
            'items'               => $items,
            'total'               => $total,
            'created_by_admin_id' => Auth::guard('admin')->id(),
        ]);

        return redirect()->route('admin.payments.invoice.show', $invoice);
    }

    public function showInvoice(string $id)
    {
        $invoice = Invoice::with('client')->findOrFail($id);

        // Belongs to one of this admin's BOs?
        if ($invoice->client->admin_id !== Auth::guard('admin')->id()) {
            abort(403);
        }

        return view('admin.payments.invoice', compact('invoice'));
    }

    private function nextInvoiceNumber(Client $client, Carbon $when): string
    {
        // Format: AGS-MMDDYYYY-NNN  where NNN is the global daily sequence
        $date = $when->format('mdY');
        $todayCount = Invoice::whereDate('invoice_date', $when->toDateString())->count();
        return sprintf('AGS-%s-%03d', $date, $todayCount + 1);
    }

    public function bulkStorePayment(Request $request)
    {
        $client = $this->scopedBO();

        $data = $request->validate([
            'end_user_ids'   => 'required|array|min:1',
            'end_user_ids.*' => ['integer', Rule::exists('end_users', 'id')->where(fn ($q) => $q->where('client_id', $client->id))],
            'round'          => 'required|integer|between:1,8',
        ]);

        $today = now()->toDateString();
        $adminId = Auth::guard('admin')->id();

        $endUsers = EndUser::with('client')
            ->whereIn('id', $data['end_user_ids'])
            ->get()
            ->keyBy('id');

        $count = 0;
        foreach ($data['end_user_ids'] as $euId) {
            $rate = isset($endUsers[$euId])
                ? $endUsers[$euId]->effectiveRoundFee((int) $data['round'])
                : (float) ($client->per_round_fee ?? 0);

            ClientPayment::updateOrCreate(
                ['end_user_id' => $euId, 'round' => $data['round']],
                [
                    'amount'              => $rate,
                    'paid_at'             => $today,
                    'created_by_admin_id' => $adminId,
                ]
            );
            $count++;
        }

        return back()->with('status', "Marked {$count} client(s) paid for Round {$data['round']}.");
    }

    /**
     * One-click "paid in full": mark every UNPAID active round, for every client
     * of this BO, as paid at each client's effective rate. Used when the BO pays
     * the whole outstanding balance at once instead of clicking chips one by one.
     * Only touches rounds a client has actually reached (same rule the unpaid
     * total uses) and skips anything already paid, so it's safe to click twice.
     */
    public function payAllUnpaid(Request $request)
    {
        $client = $this->scopedBO();

        $endUsers = EndUser::forClient($client->id)
            ->clientsList()
            ->with('payments')
            ->get();
        $endUsers->each(fn ($eu) => $eu->setRelation('client', $client));

        $roundLabelToNum = [
            '1st Round' => 1, '2nd Round' => 2, '3rd Round' => 3, '4th Round' => 4,
            '5th Round' => 5, '6th Round' => 6, '7th Round' => 7, '8th Round' => 8,
        ];

        $today   = now()->toDateString();
        $adminId = Auth::guard('admin')->id();
        $count   = 0;

        foreach ($endUsers as $eu) {
            $paidByRound = $eu->payments->keyBy('round');

            $activeRounds = collect($eu->rounds ?? [])
                ->map(fn ($label) => $roundLabelToNum[$label] ?? null)
                ->filter()
                ->values()
                ->all();
            if (empty($activeRounds)) {
                $activeRounds = [1];
            }

            foreach ($activeRounds as $rn) {
                if ($paidByRound->has($rn)) {
                    continue;   // already paid — leave it
                }
                ClientPayment::updateOrCreate(
                    ['end_user_id' => $eu->id, 'round' => $rn],
                    [
                        'amount'              => $eu->effectiveRoundFee((int) $rn),
                        'paid_at'             => $today,
                        'created_by_admin_id' => $adminId,
                    ]
                );
                $count++;
            }
        }

        if ($count === 0) {
            return back()->with('status', 'Nothing to mark — every reached round is already paid.');
        }

        return back()->with('confirm', "All balances paid — {$count} round(s) marked across all clients");
    }

    /**
     * Set (or clear) a single client's custom per-round fee. A blank/empty
     * value resets the client back to the BO's default per_round_fee.
     */
    public function updateEndUserFee(Request $request, string $id)
    {
        $client = $this->scopedBO();

        $endUser = EndUser::forClient($client->id)->findOrFail($id);

        $data = $request->validate([
            'per_round_fee' => 'nullable|numeric|min:0|max:100000',
        ]);

        $fee = $data['per_round_fee'] ?? null;
        $endUser->update([
            'per_round_fee' => ($fee === null || $fee === '') ? null : $fee,
        ]);

        return back()->with('status', $endUser->per_round_fee === null
            ? "{$endUser->full_name} reset to the default rate."
            : "{$endUser->full_name} set to \${$endUser->per_round_fee} per round.");
    }

    /**
     * Set (or clear) a custom fee for a single round of one client, e.g. $12
     * for Round 1 only while the other rounds keep the default. With
     * apply_all=1 the amount is written as the client's flat per-round rate
     * (all rounds) and any per-round overrides are cleared. A blank amount
     * clears just that round's override (it falls back to the default).
     */
    public function updateRoundFee(Request $request, string $id)
    {
        $client = $this->scopedBO();

        $endUser = EndUser::forClient($client->id)->findOrFail($id);

        $data = $request->validate([
            'round'         => 'required|integer|between:1,8',
            'per_round_fee' => 'nullable|numeric|min:0|max:100000',
            'apply_all'     => 'nullable|boolean',
        ]);

        $raw = $data['per_round_fee'] ?? null;
        $fee = ($raw === null || $raw === '') ? null : (float) $raw;
        $round = (int) $data['round'];

        // "Apply to all rounds" — store as the flat client rate and drop any
        // per-round overrides so there is a single source of truth.
        if (!empty($data['apply_all'])) {
            $endUser->update([
                'per_round_fee'  => $fee,
                'per_round_fees' => null,
            ]);

            return back()->with('status', $fee === null
                ? "{$endUser->full_name} reset to the default rate for all rounds."
                : "{$endUser->full_name} set to \${$fee} per round (all rounds).");
        }

        $overrides = $endUser->per_round_fees ?? [];
        if ($fee === null) {
            unset($overrides[(string) $round]);
        } else {
            $overrides[(string) $round] = $fee;
        }
        // Persist null when empty so the column stays clean.
        $endUser->update(['per_round_fees' => $overrides === [] ? null : $overrides]);

        return back()->with('status', $fee === null
            ? "{$endUser->full_name} Round {$round} reset to the default rate."
            : "{$endUser->full_name} Round {$round} set to \${$fee}.");
    }

    public function updatePayment(Request $request, string $id)
    {
        $payment = ClientPayment::forClient($this->scopedBO()->id)->findOrFail($id);

        $data = $request->validate([
            'amount'  => 'required|numeric|min:0',
            'paid_at' => 'required|date',
            'method'  => 'nullable|string|max:50',
            'notes'   => 'nullable|string|max:1000',
        ]);

        $payment->update($data);

        return back()->with('status', 'Payment updated.');
    }

    public function destroyPayment(string $id)
    {
        ClientPayment::forClient($this->scopedBO()->id)->findOrFail($id)->delete();
        return back()->with('status', 'Payment removed.');
    }

    /* =============== HOURLY — MANUAL HOURS PER PERIOD =============== */

    public function storePeriodHours(Request $request)
    {
        $client = $this->scopedBO();

        $data = $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
            'hours'        => 'required|numeric|min:0|max:1000',
        ]);

        PeriodHours::updateOrCreate(
            ['client_id' => $client->id, 'period_start' => $data['period_start']],
            ['period_end' => $data['period_end'], 'hours' => $data['hours']]
        );

        return back()->with('status', 'Hours updated.');
    }

    /* =============== HOURLY — TIME ENTRIES (legacy per-day) =============== */

    public function storeTime(Request $request)
    {
        $client = $this->scopedBO();

        $data = $request->validate([
            'work_date'   => 'required|date',
            'hours'       => 'required|numeric|min:0.25|max:24',
            'description' => 'nullable|string|max:1000',
        ]);

        $data['client_id'] = $client->id;
        $data['created_by_admin_id'] = Auth::guard('admin')->id();

        TimeEntry::create($data);

        return back()->with('status', 'Time logged.');
    }

    public function destroyTime(string $id)
    {
        TimeEntry::where('client_id', $this->scopedBO()->id)->findOrFail($id)->delete();
        return back()->with('status', 'Time entry removed.');
    }

    /* =============== HOURLY — PAYOUTS =============== */

    public function storePayout(Request $request)
    {
        $client = $this->scopedBO();

        $data = $request->validate([
            'period_start'    => 'required|date',
            'period_end'      => 'required|date|after_or_equal:period_start',
            'hours_in_period' => 'required|numeric|min:0',
            'amount_paid'     => 'required|numeric|min:0',
            'paid_at'         => 'required|date',
            'method'          => 'nullable|string|max:50',
            'notes'           => 'nullable|string|max:1000',
        ]);

        $data['client_id'] = $client->id;
        $data['created_by_admin_id'] = Auth::guard('admin')->id();

        TimePayout::updateOrCreate(
            [
                'client_id'    => $client->id,
                'period_start' => $data['period_start'],
                'period_end'   => $data['period_end'],
            ],
            $data
        );

        return back()->with('status', 'Payout recorded.');
    }

    public function destroyPayout(string $id)
    {
        TimePayout::where('client_id', $this->scopedBO()->id)->findOrFail($id)->delete();
        return back()->with('status', 'Payout removed.');
    }

    /* =============== HELPERS =============== */

    private function scopedBO(): Client
    {
        $clientId = session('selected_client_id');
        return Client::forAdmin(Auth::guard('admin')->id())->findOrFail($clientId);
    }

    private function buildPerRoundData(Client $client): array
    {
        $endUsers = EndUser::forClient($client->id)
            ->clientsList()   // only real Clients — not New Clients / Errors
            ->with('payments')
            ->orderBy('first_name')
            ->get();
        // effectiveRoundFee() reads $eu->client; preload it on every row.
        $endUsers->each(fn ($eu) => $eu->setRelation('client', $client));

        $rate = (float) ($client->per_round_fee ?? 0);

        $roundLabelToNum = [
            '1st Round' => 1, '2nd Round' => 2, '3rd Round' => 3, '4th Round' => 4,
            '5th Round' => 5, '6th Round' => 6, '7th Round' => 7, '8th Round' => 8,
        ];

        $unpaidItems   = [];
        $unpaidByRound = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0, 6=>0, 7=>0, 8=>0];
        $totalUnpaid   = 0.0;
        // Rounds done free of charge. They're "paid" at $0 so they never show as
        // unpaid — but the BO should still see the round was delivered as a
        // courtesy, so they ride along on the invoice at $0.00.
        $freeItems     = [];

        $rows = $endUsers->map(function ($eu) use ($roundLabelToNum, &$unpaidItems, &$unpaidByRound, &$totalUnpaid, &$freeItems) {
            $euRate = $eu->effectiveRoundFee();
            $paidByRound = $eu->payments->keyBy('round');

            // Active rounds = rounds this client has actually reached/done.
            $activeRounds = collect($eu->rounds ?? [])
                ->map(fn ($label) => $roundLabelToNum[$label] ?? null)
                ->filter()
                ->values()
                ->all();
            // If no rounds set, treat them as being in Round 1 by default
            if (empty($activeRounds)) {
                $activeRounds = [1];
            }

            $cells = [];
            for ($r = 1; $r <= 8; $r++) {
                $isPaid = $paidByRound->has($r);
                $cells[$r] = [
                    'state'   => $isPaid ? 'paid' : 'unpaid',
                    'payment' => $paidByRound->get($r),
                    'rate'    => $eu->effectiveRoundFee($r),
                    'custom'  => $eu->hasCustomRoundFee($r),
                    // "due" = this round has been done by the client but is not
                    // paid yet → chip blinks as a collect-the-money hint.
                    'due'     => !$isPaid && in_array($r, $activeRounds, true),
                ];
            }

            $latestRound = !empty($activeRounds) ? max($activeRounds) : 1;

            foreach ($activeRounds as $rn) {
                if (!$paidByRound->has($rn)) {
                    $rnRate = $eu->effectiveRoundFee($rn);
                    $unpaidItems[] = [
                        'name'   => $eu->full_name,
                        'email'  => $eu->email,
                        'round'  => $rn,
                        'amount' => $rnRate,
                    ];
                    $unpaidByRound[$rn]++;
                    $totalUnpaid += $rnRate;
                    continue;
                }

                // Done free of charge. Only surface it while it's the client's
                // latest round — once a later round is worked, that round bills
                // instead and this courtesy has already been shown.
                $payment = $paidByRound->get($rn);
                if ($rn === $latestRound && $payment && $payment->is_free) {
                    $freeItems[] = [
                        'name'   => $eu->full_name,
                        'email'  => $eu->email,
                        'round'  => $rn,
                        'amount' => 0.0,
                        'free'   => true,
                    ];
                }
            }

            return [
                'end_user'   => $eu,
                'cells'      => $cells,
                'rate'       => $euRate,
                'custom_fee' => $eu->hasCustomRoundFee(),
                'total_paid' => (float) $eu->payments->sum('amount'),
            ];
        });

        $allPayments = ClientPayment::forClient($client->id)->get();
        $earnedTotal     = (float) $allPayments->sum('amount');
        $earnedThisMonth = (float) $allPayments->where('paid_at', '>=', now()->startOfMonth()->toDateString())->sum('amount');

        return [
            'rows'            => $rows,
            'rate'            => $rate,
            'earnedTotal'     => $earnedTotal,
            'earnedThisMonth' => $earnedThisMonth,
            'totalUnpaid'     => $totalUnpaid,
            'unpaidItems'     => $unpaidItems,
            'unpaidByRound'   => $unpaidByRound,
            'freeItems'       => $freeItems,
        ];
    }

    private function buildHourlyData(Client $client): array
    {
        $rate   = (float) ($client->hourly_rate ?? 0);
        $cycle  = $client->pay_cycle ?: 'biweekly';
        $anchor = $client->pay_cycle_anchor ? $client->pay_cycle_anchor->copy() : now()->startOfWeek();

        [$currentStart, $currentEnd] = $this->computePeriod($anchor, $cycle, now());

        $hoursThisPeriod = $this->periodHours($client, $currentStart, $currentEnd);

        // Build the last 6 pay periods, anchored to the cycle start date.
        $periods = [];
        $cursorEnd = $currentEnd->copy();
        $cursorStart = $currentStart->copy();
        for ($i = 0; $i < 6; $i++) {
            $hoursIn = $this->periodHours($client, $cursorStart, $cursorEnd);
            $payout = TimePayout::where('client_id', $client->id)
                ->where('period_start', $cursorStart->toDateString())
                ->where('period_end', $cursorEnd->toDateString())
                ->first();
            $periods[] = [
                'start'    => $cursorStart->copy(),
                'end'      => $cursorEnd->copy(),
                'hours'    => $hoursIn,
                'expected' => round($hoursIn * $rate, 2),
                'payout'   => $payout,
                'is_current' => $cursorStart->equalTo($currentStart),
            ];
            [$cursorStart, $cursorEnd] = $this->previousPeriod($cursorStart, $cursorEnd, $cycle);
        }

        $allPayouts = TimePayout::where('client_id', $client->id)->get();
        $earnedTotal      = (float) $allPayouts->sum('amount_paid');
        $earnedThisMonth  = (float) $allPayouts->where('paid_at', '>=', now()->startOfMonth()->toDateString())->sum('amount_paid');

        return [
            'rate'             => $rate,
            'cycle'            => $cycle,
            'currentStart'     => $currentStart,
            'currentEnd'       => $currentEnd,
            'hoursThisPeriod'  => $hoursThisPeriod,
            'expectedNow'      => round($hoursThisPeriod * $rate, 2),
            'periods'          => $periods,
            'earnedTotal'      => $earnedTotal,
            'earnedThisMonth'  => $earnedThisMonth,
            'weeklyHoursTarget'=> (int) ($client->weekly_hours_target ?? 0),
        ];
    }

    /**
     * Hours for a single period: the manually-entered total if one exists,
     * otherwise a fallback sum of any legacy per-day time entries in range
     * (so previously-logged hours are not lost after the switch).
     */
    private function periodHours(Client $client, Carbon $start, Carbon $end): float
    {
        $row = PeriodHours::where('client_id', $client->id)
            ->where('period_start', $start->toDateString())
            ->first();
        if ($row) {
            return (float) $row->hours;
        }
        return (float) TimeEntry::where('client_id', $client->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->sum('hours');
    }

    private function computePeriod(Carbon $anchor, string $cycle, Carbon $today): array
    {
        $anchor = $anchor->copy()->startOfDay();
        $today  = $today->copy()->startOfDay();

        if ($cycle === 'monthly') {
            // Anchored monthly windows: [anchor, anchor+1mo-1d], [anchor+1mo, …]
            $start = $anchor->copy();
            while ($start->copy()->addMonthNoOverflow()->lessThanOrEqualTo($today)) {
                $start->addMonthNoOverflow();
            }
            $end = $start->copy()->addMonthNoOverflow()->subDay();
            return [$start, $end];
        }

        // biweekly — 14-day windows from the anchor
        $diff   = (int) $anchor->diffInDays($today, false);
        $offset = intdiv(max(0, $diff), 14);
        $start  = $anchor->copy()->addDays($offset * 14);
        $end    = $start->copy()->addDays(13);
        return [$start, $end];
    }

    private function previousPeriod(Carbon $start, Carbon $end, string $cycle): array
    {
        if ($cycle === 'monthly') {
            $prevStart = $start->copy()->subMonthNoOverflow();
            $prevEnd   = $start->copy()->subDay();
            return [$prevStart, $prevEnd];
        }
        $prevEnd   = $start->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays(13);
        return [$prevStart, $prevEnd];
    }
}
