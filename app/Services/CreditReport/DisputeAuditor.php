<?php

namespace App\Services\CreditReport;

/**
 * Applies the dispute-audit rules to parsed report data and marks which items
 * are candidates for dispute letters ("red").
 *
 * Rules (from the agreed spec):
 *
 * Account classification per bureau → collection | charge_off | closed_late |
 * open_late | open | closed_positive, from Account Status + Payment Status +
 * Comments + Past Due + payment-history late marks.
 *
 * Red (add to dispute letters):
 *   collection, charge_off, closed_late  → always
 *   open_late                            → yes (late-payment dispute letters)
 *   open, closed_positive                → no
 * An account is red if ANY bureau classifies negative.
 * Bankruptcy (public record)             → always red.
 *
 * Positive account (for inquiry matching): open, open_late, or closed_positive.
 * Inquiry is red only when its creditor name matches NO positive account AND
 * its date matches NO positive account's open date (a single positive match by
 * name OR date clears it).
 */
class DisputeAuditor
{
    public const NEGATIVE = ['collection', 'charge_off', 'closed_late', 'open_late'];

    public function audit(array $parsed): array
    {
        $accounts = array_map(fn ($a) => $this->auditAccount($a), $parsed['accounts']);

        // Positive accounts feed inquiry matching.
        $positive = array_values(array_filter($accounts, fn ($a) => ! $a['is_negative']));
        $posNames = array_map(fn ($a) => $this->norm($a['creditor']), $positive);
        $posDates = [];
        foreach ($positive as $a) {
            foreach ($a['bureaus'] as $f) {
                if (! empty($f['date_opened'])) $posDates[$this->normDate($f['date_opened'])] = true;
            }
        }

        $inquiries = array_map(function ($q) use ($posNames, $posDates) {
            $nameMatch = in_array($this->norm($q['creditor']), $posNames, true)
                || $this->fuzzyIn($this->norm($q['creditor']), $posNames);
            $dateMatch = isset($posDates[$this->normDate($q['date'] ?? '')]);
            $red = ! $nameMatch && ! $dateMatch;
            return $q + [
                'is_negative' => $red,
                'auto_reason' => $red
                    ? 'No matching positive account by name or date'
                    : ($nameMatch ? 'Matches a positive account name' : 'Matches a positive account open date'),
            ];
        }, $parsed['inquiries']);

        $records = array_map(function ($p) {
            $bk = $p['type'] === 'bankruptcy';
            return $p + ['is_negative' => $bk, 'auto_reason' => $bk ? 'Bankruptcy is always negative' : null];
        }, $parsed['public_records']);

        return [
            'consumer'       => $parsed['consumer'],
            'accounts'       => $accounts,
            'inquiries'      => $inquiries,
            'public_records' => $records,
            'addresses'      => $parsed['addresses'],   // never auto-flagged; admin selects
        ];
    }

    /* --------------------------------------------------------------------- */

    private function auditAccount(array $a): array
    {
        $perBureau = [];
        foreach ($a['bureaus'] as $bureau => $f) {
            $perBureau[$bureau] = $this->classify($f, $a['history'][$bureau]['late'] ?? 0);
        }

        $classes = array_values($perBureau);
        $negative = (bool) array_intersect($classes, self::NEGATIVE);

        // most-severe class drives the label / reason
        $order = ['collection', 'charge_off', 'closed_late', 'open_late', 'open', 'closed_positive', 'unknown'];
        usort($classes, fn ($x, $y) => array_search($x, $order) <=> array_search($y, $order));
        $worst = $classes[0] ?? 'unknown';

        return $a + [
            'per_bureau'  => $perBureau,
            'category'    => $worst,
            'is_negative' => $negative,
            'auto_reason' => $negative ? $this->reasonFor($worst) : null,
        ];
    }

    /** One bureau's fields → a class. */
    private function classify(array $f, int $historyLate): string
    {
        $status  = strtolower($f['account_status'] ?? '');
        $pay     = strtolower($f['payment_status'] ?? '');
        $comment = strtolower($f['comments'] ?? '');
        $type    = strtolower(($f['account_type'] ?? '') . ' ' . ($f['account_type_detail'] ?? ''));
        $blob    = "$status $pay $comment $type";
        $pastDue = $this->money($f['past_due'] ?? '');

        if (str_contains($blob, 'collection')) return 'collection';
        if (preg_match('/charge[\s-]?off|charged off/', $blob)) return 'charge_off';

        $isClosed = str_contains($status, 'closed') || str_contains($status, 'paid') && ! str_contains($status, 'open');
        $isLate   = $pastDue > 0
            || $historyLate > 0
            || preg_match('/past due|delinquent|late|was past|derogatory|seriously/', $blob) === 1;

        // "was past due" with a now-current status still counts as a late history
        if ($isLate) return $isClosed ? 'closed_late' : 'open_late';
        if ($isClosed) return 'closed_positive';
        if (str_contains($status, 'open') || $pay !== '') return 'open';
        return 'unknown';
    }

    private function reasonFor(string $class): string
    {
        return [
            'collection'  => 'Collection account',
            'charge_off'  => 'Charge-off account',
            'closed_late' => 'Closed account with late payments',
            'open_late'   => 'Open account with late payments',
        ][$class] ?? 'Negative account';
    }

    private function norm(?string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($s ?? ''));
    }

    /** loose containment: inquiry name shares a 4+ char stem with an account name */
    private function fuzzyIn(string $needle, array $haystack): bool
    {
        if (mb_strlen($needle) < 4) return false;
        $stem = substr($needle, 0, 5);
        foreach ($haystack as $h) {
            if ($h !== '' && (str_contains($h, $stem) || str_contains($needle, substr($h, 0, 5)))) return true;
        }
        return false;
    }

    private function normDate(string $d): string
    {
        return preg_replace('/\D/', '', $d);      // 04/28/2026 -> 04282026
    }

    private function money(string $v): float
    {
        return (float) preg_replace('/[^0-9.]/', '', $v);
    }
}
