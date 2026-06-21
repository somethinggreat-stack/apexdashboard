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
            'round'       => 'required|integer|between:1,5',
            'amount'      => 'nullable|numeric|min:0',
            'paid_at'     => 'nullable|date',
            'method'      => 'nullable|string|max:50',
            'notes'       => 'nullable|string|max:1000',
        ]);

        // Sensible defaults — VA can mark paid in 1 click without filling a form.
        // Default amount uses the client's effective rate (their custom per-round
        // fee if set, otherwise the BO default).
        if ($data['amount'] === null) {
            $endUser = EndUser::with('client')->find($data['end_user_id']);
            $data['amount'] = $endUser ? $endUser->effectiveRoundFee() : (float) ($client->per_round_fee ?? 0);
        }
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

        if (empty($data['unpaidItems'])) {
            return back()->with('status', 'No unpaid items to invoice — everything is already paid.');
        }

        $invoice = Invoice::create([
            'client_id'           => $client->id,
            'invoice_number'      => $this->nextInvoiceNumber($client, now()),
            'invoice_date'        => now()->toDateString(),
            'items'               => $data['unpaidItems'],
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
            'round'          => 'required|integer|between:1,5',
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
                ? $endUsers[$euId]->effectiveRoundFee()
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

        $endUser->update([
            'per_round_fee' => ($data['per_round_fee'] === null || $data['per_round_fee'] === '')
                ? null
                : $data['per_round_fee'],
        ]);

        return back()->with('status', $endUser->per_round_fee === null
            ? "{$endUser->full_name} reset to the default rate."
            : "{$endUser->full_name} set to \${$endUser->per_round_fee} per round.");
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
            ->with('payments')
            ->orderBy('first_name')
            ->get();
        // effectiveRoundFee() reads $eu->client; preload it on every row.
        $endUsers->each(fn ($eu) => $eu->setRelation('client', $client));

        $rate = (float) ($client->per_round_fee ?? 0);

        $roundLabelToNum = [
            '1st Round' => 1, '2nd Round' => 2, '3rd Round' => 3,
            '4th Round' => 4, '5th Round' => 5,
        ];

        $unpaidItems   = [];
        $unpaidByRound = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0];
        $totalUnpaid   = 0.0;

        $rows = $endUsers->map(function ($eu) use ($roundLabelToNum, &$unpaidItems, &$unpaidByRound, &$totalUnpaid) {
            $euRate = $eu->effectiveRoundFee();
            $paidByRound = $eu->payments->keyBy('round');
            $cells = [];
            for ($r = 1; $r <= 5; $r++) {
                $cells[$r] = [
                    'state'   => $paidByRound->has($r) ? 'paid' : 'unpaid',
                    'payment' => $paidByRound->get($r),
                ];
            }

            // Active rounds = rounds this client is actually in
            $activeRounds = collect($eu->rounds ?? [])
                ->map(fn ($label) => $roundLabelToNum[$label] ?? null)
                ->filter()
                ->values()
                ->all();
            // If no rounds set, treat them as being in Round 1 by default
            if (empty($activeRounds)) {
                $activeRounds = [1];
            }

            foreach ($activeRounds as $rn) {
                if (!$paidByRound->has($rn)) {
                    $unpaidItems[] = [
                        'name'   => $eu->full_name,
                        'email'  => $eu->email,
                        'round'  => $rn,
                        'amount' => $euRate,
                    ];
                    $unpaidByRound[$rn]++;
                    $totalUnpaid += $euRate;
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
