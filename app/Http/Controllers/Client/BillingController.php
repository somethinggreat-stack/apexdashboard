<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientPayment;
use App\Models\EndUser;
use App\Models\Invoice;
use App\Models\PeriodHours;
use App\Models\TimePayout;
use Illuminate\Support\Facades\Auth;

class BillingController extends Controller
{
    /**
     * Read-only billing transparency for the business owner: a full picture of
     * what they've been invoiced, what's been paid, and what's still
     * outstanding (unpaid). No edit actions.
     */
    public function index()
    {
        $client = Auth::guard('client')->user();
        $model  = $client->compensation_model ?: 'per_round';
        $monthStart = now()->startOfMonth()->toDateString();

        $invoices = Invoice::where('client_id', $client->id)
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get();

        if ($model === 'hourly') {
            $payouts = TimePayout::where('client_id', $client->id)
                ->orderByDesc('period_start')
                ->get();

            $totalPaid     = (float) $payouts->sum('amount_paid');
            $paidThisMonth = (float) $payouts->where('paid_at', '>=', $monthStart)->sum('amount_paid');

            // Outstanding = everything earned (hours logged × rate) that hasn't
            // been paid out yet.
            $rate          = (float) ($client->hourly_rate ?? 0);
            $hoursLogged   = (float) PeriodHours::where('client_id', $client->id)->sum('hours');
            $earnedTotal   = round($hoursLogged * $rate, 2);
            $outstanding   = max(0, round($earnedTotal - $totalPaid, 2));

            $stats = [
                ['label' => 'Total Paid',       'value' => '$' . number_format($totalPaid, 2),     'tone' => 'green'],
                ['label' => 'Outstanding',      'value' => '$' . number_format($outstanding, 2),   'tone' => 'orange'],
                ['label' => 'Paid This Month',  'value' => '$' . number_format($paidThisMonth, 2), 'tone' => ''],
                ['label' => 'Hourly Rate',      'value' => '$' . number_format($rate, 2),          'tone' => ''],
                ['label' => 'Payouts Recorded', 'value' => $payouts->count(),                      'tone' => ''],
            ];

            return view('client.billing.index', compact(
                'client', 'model', 'invoices', 'payouts', 'stats', 'outstanding'
            ));
        }

        // per-round
        $payments = ClientPayment::forClient($client->id)
            ->with('endUser')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $totalPaid     = (float) $payments->sum('amount');
        $paidThisMonth = (float) $payments->where('paid_at', '>=', $monthStart)->sum('amount');

        // Outstanding: for every client (end user), each round they're active in
        // that has no recorded payment is owed at the per-round fee.
        $outstanding = $this->buildPerRoundOutstanding($client);

        $stats = [
            ['label' => 'Total Paid',      'value' => '$' . number_format($totalPaid, 2),               'tone' => 'green'],
            ['label' => 'Outstanding',     'value' => '$' . number_format($outstanding['total'], 2),    'tone' => 'orange'],
            ['label' => 'Paid This Month', 'value' => '$' . number_format($paidThisMonth, 2),           'tone' => ''],
            ['label' => 'Fee per Round',   'value' => '$' . number_format((float) ($client->per_round_fee ?? 0), 2), 'tone' => ''],
            ['label' => 'Rounds Paid',     'value' => $payments->count(),                               'tone' => ''],
        ];

        return view('client.billing.index', compact(
            'client', 'model', 'invoices', 'payments', 'stats', 'outstanding'
        ));
    }

    /**
     * Mirrors the admin Payments page logic, read-only: which clients owe which
     * rounds and the total still unpaid.
     */
    private function buildPerRoundOutstanding($client): array
    {
        $roundLabelToNum = [
            '1st Round' => 1, '2nd Round' => 2, '3rd Round' => 3,
            '4th Round' => 4, '5th Round' => 5,
        ];

        $endUsers = EndUser::forClient($client->id)
            ->clientsList()   // only real Clients — not New Clients / Errors
            ->with('payments')
            ->orderBy('first_name')
            ->get();
        // effectiveRoundFee() reads $eu->client; preload it on every row.
        $endUsers->each(fn ($eu) => $eu->setRelation('client', $client));

        $items   = [];
        $byRound = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $total   = 0.0;

        foreach ($endUsers as $eu) {
            $euRate = $eu->effectiveRoundFee();
            $paidByRound = $eu->payments->keyBy('round');

            $activeRounds = collect($eu->rounds ?? [])
                ->map(fn ($label) => $roundLabelToNum[$label] ?? null)
                ->filter()
                ->values()
                ->all();
            if (empty($activeRounds)) {
                $activeRounds = [1]; // no rounds set → treat as Round 1
            }

            foreach ($activeRounds as $rn) {
                if (!$paidByRound->has($rn)) {
                    $items[] = [
                        'name'   => $eu->full_name,
                        'round'  => $rn,
                        'amount' => $euRate,
                    ];
                    $byRound[$rn]++;
                    $total += $euRate;
                }
            }
        }

        return [
            'items'   => $items,
            'count'   => count($items),
            'total'   => $total,
            'byRound' => $byRound,
        ];
    }

    /**
     * View one of this BO's own invoices (the printable page).
     */
    public function showInvoice(string $id)
    {
        $client  = Auth::guard('client')->user();
        $invoice = Invoice::with('client')->findOrFail($id);

        if ($invoice->client_id !== $client->id) {
            abort(403);
        }

        return view('admin.payments.invoice', compact('invoice'));
    }
}
