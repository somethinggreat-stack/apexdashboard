<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\EndUser;
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

        // Sensible defaults — VA can mark paid in 1 click without filling a form
        $data['amount']  = $data['amount']  ?? (float) ($client->per_round_fee ?? 0);
        $data['paid_at'] = $data['paid_at'] ?? now()->toDateString();
        $data['created_by_admin_id'] = Auth::guard('admin')->id();

        ClientPayment::updateOrCreate(
            ['end_user_id' => $data['end_user_id'], 'round' => $data['round']],
            $data
        );

        return back()->with('status', 'Marked paid.');
    }

    public function bulkStorePayment(Request $request)
    {
        $client = $this->scopedBO();

        $data = $request->validate([
            'end_user_ids'   => 'required|array|min:1',
            'end_user_ids.*' => ['integer', Rule::exists('end_users', 'id')->where(fn ($q) => $q->where('client_id', $client->id))],
            'round'          => 'required|integer|between:1,5',
        ]);

        $rate = (float) ($client->per_round_fee ?? 0);
        $today = now()->toDateString();
        $adminId = Auth::guard('admin')->id();

        $count = 0;
        foreach ($data['end_user_ids'] as $euId) {
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

    /* =============== HOURLY — TIME ENTRIES =============== */

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

        $rate = (float) ($client->per_round_fee ?? 0);

        $rows = $endUsers->map(function ($eu) {
            $paidByRound = $eu->payments->keyBy('round');
            $cells = [];
            for ($r = 1; $r <= 5; $r++) {
                $cells[$r] = [
                    'state'   => $paidByRound->has($r) ? 'paid' : 'unpaid',
                    'payment' => $paidByRound->get($r),
                ];
            }
            $totalPaid = (float) $eu->payments->sum('amount');
            return [
                'end_user'   => $eu,
                'cells'      => $cells,
                'total_paid' => $totalPaid,
            ];
        });

        $allPayments = ClientPayment::forClient($client->id)->get();
        $earnedTotal      = (float) $allPayments->sum('amount');
        $earnedThisMonth  = (float) $allPayments->where('paid_at', '>=', now()->startOfMonth()->toDateString())->sum('amount');

        return [
            'rows'            => $rows,
            'rate'            => $rate,
            'earnedTotal'     => $earnedTotal,
            'earnedThisMonth' => $earnedThisMonth,
        ];
    }

    private function buildHourlyData(Client $client): array
    {
        $rate   = (float) ($client->hourly_rate ?? 0);
        $cycle  = $client->pay_cycle ?: 'biweekly';
        $anchor = $client->pay_cycle_anchor ? $client->pay_cycle_anchor->copy() : now()->startOfWeek();

        [$currentStart, $currentEnd] = $this->computePeriod($anchor, $cycle, now());

        $entries = TimeEntry::where('client_id', $client->id)
            ->orderByDesc('work_date')
            ->limit(60)
            ->get();

        $entriesByDay = $entries->groupBy(fn ($e) => $e->work_date?->toDateString());

        $hoursThisPeriod = (float) $entries
            ->where('work_date', '>=', $currentStart->toDateString())
            ->where('work_date', '<=', $currentEnd->toDateString())
            ->sum('hours');

        // Build the last 6 pay periods
        $periods = [];
        $cursorEnd = $currentEnd->copy();
        $cursorStart = $currentStart->copy();
        for ($i = 0; $i < 6; $i++) {
            $hoursIn = (float) TimeEntry::where('client_id', $client->id)
                ->whereBetween('work_date', [$cursorStart->toDateString(), $cursorEnd->toDateString()])
                ->sum('hours');
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
            'entriesByDay'     => $entriesByDay,
            'recentEntries'    => $entries,
            'periods'          => $periods,
            'earnedTotal'      => $earnedTotal,
            'earnedThisMonth'  => $earnedThisMonth,
            'weeklyHoursTarget'=> (int) ($client->weekly_hours_target ?? 0),
        ];
    }

    private function computePeriod(Carbon $anchor, string $cycle, Carbon $today): array
    {
        if ($cycle === 'monthly') {
            return [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()];
        }
        // biweekly
        $anchor = $anchor->copy()->startOfDay();
        $today  = $today->copy()->startOfDay();
        $diff   = (int) $anchor->diffInDays($today, false);
        $offset = intdiv(max(0, $diff), 14);
        $start  = $anchor->copy()->addDays($offset * 14);
        $end    = $start->copy()->addDays(13);
        return [$start, $end];
    }

    private function previousPeriod(Carbon $start, Carbon $end, string $cycle): array
    {
        if ($cycle === 'monthly') {
            $prevEnd = $start->copy()->subDay()->endOfMonth();
            $prevStart = $prevEnd->copy()->startOfMonth();
            return [$prevStart, $prevEnd];
        }
        $prevEnd   = $start->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays(13);
        return [$prevStart, $prevEnd];
    }
}
