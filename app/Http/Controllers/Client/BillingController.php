<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientPayment;
use App\Models\Invoice;
use App\Models\TimePayout;
use Illuminate\Support\Facades\Auth;

class BillingController extends Controller
{
    /**
     * Read-only billing transparency for the business owner: what they've
     * been invoiced and what's been recorded as paid. No edit actions.
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

            $stats = [
                ['label' => 'Total Paid',       'value' => '$' . number_format($totalPaid, 2),     'tone' => 'green'],
                ['label' => 'Paid This Month',  'value' => '$' . number_format($paidThisMonth, 2), 'tone' => ''],
                ['label' => 'Hourly Rate',      'value' => '$' . number_format((float) ($client->hourly_rate ?? 0), 2), 'tone' => ''],
                ['label' => 'Payouts Recorded', 'value' => $payouts->count(),                      'tone' => ''],
            ];

            return view('client.billing.index', compact('client', 'model', 'invoices', 'payouts', 'stats'));
        }

        // per-round
        $payments = ClientPayment::forClient($client->id)
            ->with('endUser')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $totalPaid     = (float) $payments->sum('amount');
        $paidThisMonth = (float) $payments->where('paid_at', '>=', $monthStart)->sum('amount');

        $stats = [
            ['label' => 'Total Paid',      'value' => '$' . number_format($totalPaid, 2),     'tone' => 'green'],
            ['label' => 'Paid This Month', 'value' => '$' . number_format($paidThisMonth, 2), 'tone' => ''],
            ['label' => 'Fee per Round',   'value' => '$' . number_format((float) ($client->per_round_fee ?? 0), 2), 'tone' => ''],
            ['label' => 'Rounds Paid',     'value' => $payments->count(),                     'tone' => ''],
        ];

        return view('client.billing.index', compact('client', 'model', 'invoices', 'payments', 'stats'));
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
