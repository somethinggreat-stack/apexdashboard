<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditReport;
use App\Models\CreditReportItem;
use App\Services\CreditReport\DisputeAuditor;
use App\Services\CreditReport\MyFreeScoreNowParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Letter Generator — super admin only (see routes: admin.super group).
 *
 * Upload a saved MyFreeScoreNow 3-bureau report → it's parsed + audited →
 * accounts / inquiries / addresses are laid out with the negatives flagged red
 * → the admin selects what to dispute and writes the reason (+ instruction for
 * accounts and inquiries). The raw HTML is parsed in memory and discarded.
 */
class LetterGeneratorController extends Controller
{
    public function index()
    {
        $reports = CreditReport::latest()->limit(50)->get();

        return view('admin.letter-generator.index', compact('reports'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'report' => 'required|file|max:20480',   // ≤20MB saved HTML
        ]);

        $file = $request->file('report');
        $ext  = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['html', 'htm'])) {
            return back()->withErrors(['report' => 'Please upload the saved report as an HTML file (.html).']);
        }

        $html = file_get_contents($file->getRealPath());
        if ($html === false || stripos($html, 'transunion') === false) {
            return back()->withErrors(['report' => "That file doesn't look like a 3-bureau credit report. Save the report page from the browser as HTML and upload that."]);
        }

        $parsed  = (new MyFreeScoreNowParser())->parse($html);
        $audited = (new DisputeAuditor())->audit($parsed);

        $report = DB::transaction(function () use ($audited) {
            $report = CreditReport::create([
                'uploaded_by_admin_id' => Auth::guard('admin')->id(),
                'consumer_name'        => $audited['consumer']['name'] ?? null,
                'report_date'          => $this->date($audited['consumer']['report_date'] ?? null),
                'source'               => 'mfsn',
            ]);

            $this->persist($report, $audited);

            $report->update([
                'account_count'  => $report->items()->where('item_type', 'account')->count(),
                'inquiry_count'  => $report->items()->where('item_type', 'inquiry')->count(),
                'negative_count' => $report->items()->where('is_negative', true)->count(),
            ]);

            return $report;
        });

        // The uploaded file is never written to disk — it lived only in memory.
        return redirect()->route('admin.letter-generator.show', $report)
            ->with('status', 'Report audited — review the flagged items below.');
    }

    public function show(string $id)
    {
        $report = CreditReport::findOrFail($id);
        $items  = $report->items()->orderBy('sort')->get()->groupBy('item_type');

        return view('admin.letter-generator.show', [
            'report'    => $report,
            'accounts'  => $items->get('account', collect()),
            'inquiries' => $items->get('inquiry', collect()),
            'records'   => $items->get('public_record', collect()),
            'addresses' => $items->get('personal_info', collect()),
        ]);
    }

    /** Save the admin's selections + reasons/instructions. */
    public function save(Request $request, string $id)
    {
        $report = CreditReport::findOrFail($id);
        $selected     = (array) $request->input('selected', []);
        $reasons      = (array) $request->input('reason', []);
        $instructions = (array) $request->input('instruction', []);

        foreach ($report->items as $item) {
            $isSel = isset($selected[$item->id]);
            $item->update([
                'selected'            => $isSel,
                'dispute_reason'      => $isSel ? trim((string) ($reasons[$item->id] ?? '')) ?: null : null,
                'dispute_instruction' => $isSel && $item->item_type !== 'personal_info'
                    ? (trim((string) ($instructions[$item->id] ?? '')) ?: null)
                    : null,
            ]);
        }

        return back()->with('status', 'Selections saved. Letter templates are the next step.');
    }

    public function destroy(string $id)
    {
        CreditReport::findOrFail($id)->delete();

        return redirect()->route('admin.letter-generator.index')->with('status', 'Report deleted.');
    }

    /* --------------------------------------------------------------------- */

    private function persist(CreditReport $report, array $audited): void
    {
        $sort = 0;

        // Negatives are pre-selected — the audit's recommendation, which the
        // admin then adjusts. Addresses are never pre-selected.
        foreach ($audited['accounts'] as $a) {
            $neg = (bool) ($a['is_negative'] ?? false);
            $report->items()->create([
                'item_type'      => 'account',
                'category'       => $a['category'] ?? null,
                'creditor_name'  => $a['creditor'] ?? null,
                'account_number' => $a['account_number'] ?? null,
                'detail'         => [
                    'bureaus'    => $a['bureaus'] ?? [],
                    'per_bureau' => $a['per_bureau'] ?? [],
                    'history'    => $a['history'] ?? [],
                ],
                'open_date'      => $this->earliestOpen($a['bureaus'] ?? []),
                'is_negative'    => $neg,
                'auto_reason'    => $a['auto_reason'] ?? null,
                'selected'       => $neg,
                'sort'           => $sort++,
            ]);
        }

        foreach ($audited['inquiries'] as $q) {
            $neg = (bool) ($q['is_negative'] ?? false);
            $report->items()->create([
                'item_type'     => 'inquiry',
                'category'      => 'inquiry',
                'creditor_name' => $q['creditor'] ?? null,
                'detail'        => ['business' => $q['business'] ?? null, 'date' => $q['date'] ?? null, 'bureau' => $q['bureau'] ?? null],
                'is_negative'   => $neg,
                'auto_reason'   => $q['auto_reason'] ?? null,
                'selected'      => $neg,
                'sort'          => $sort++,
            ]);
        }

        foreach ($audited['public_records'] as $p) {
            $neg = (bool) ($p['is_negative'] ?? false);
            $report->items()->create([
                'item_type'     => 'public_record',
                'category'      => $p['type'] ?? 'public record',
                'creditor_name' => ucfirst($p['type'] ?? 'Public record'),
                'detail'        => ['raw' => $p['raw'] ?? null],
                'is_negative'   => $neg,
                'auto_reason'   => $p['auto_reason'] ?? null,
                'selected'      => $neg,
                'sort'          => $sort++,
            ]);
        }

        foreach ($audited['addresses'] as $addr) {
            $report->items()->create([
                'item_type'     => 'personal_info',
                'category'      => 'address',
                'creditor_name' => $addr['line'] ?? null,
                'detail'        => [],
                'is_negative'   => false,
                'sort'          => $sort++,
            ]);
        }
    }

    private function earliestOpen(array $bureaus): ?string
    {
        $dates = [];
        foreach ($bureaus as $f) {
            if (! empty($f['date_opened']) && ($d = $this->date($f['date_opened']))) $dates[] = $d;
        }
        sort($dates);
        return $dates[0] ?? null;
    }

    private function date(?string $s): ?string
    {
        if (! $s) return null;
        try { return \Carbon\Carbon::parse($s)->toDateString(); } catch (\Throwable) { return null; }
    }
}
