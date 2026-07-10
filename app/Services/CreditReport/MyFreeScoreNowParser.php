<?php

namespace App\Services\CreditReport;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Parses a saved MyFreeScoreNow / Equifax "Three Bureau Credit Report" HTML
 * export into structured, non-identifying data.
 *
 * The report is an AngularJS-rendered page; once saved from the browser the
 * values are baked into the DOM as text, so plain DOM parsing works. Each
 * account is a `table.rpt_content_table` with a TransUnion | Experian | Equifax
 * column layout and one field per row.
 *
 * Returns:
 *   [
 *     'consumer'       => ['name' => ?, 'report_date' => ?],
 *     'accounts'       => [ ['creditor'=>, 'account_number'=>, 'bureaus'=>['TransUnion'=>[field=>val,…], …], 'history'=>['TransUnion'=>['late30'=>n,…]]], … ],
 *     'inquiries'      => [ ['creditor'=>, 'business'=>, 'date'=>, 'bureau'=>], … ],
 *     'public_records' => [ ['type'=>, 'bureaus'=>[…]], … ],
 *     'addresses'      => [ ['line'=>, 'bureau'=>], … ],
 *   ]
 */
class MyFreeScoreNowParser
{
    private const BUREAUS = ['TransUnion', 'Experian', 'Equifax'];

    /** Payment-history CSS classes that mean a late mark. */
    private const LATE_CLASSES = ['hstry-30', 'hstry-60', 'hstry-90', 'hstry-120'];

    public function parse(string $html): array
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML($html);
        $xp = new DOMXPath($doc);

        return [
            'consumer'       => $this->consumer($xp),
            'accounts'       => $this->accounts($xp),
            'inquiries'      => $this->inquiries($xp),
            'public_records' => $this->publicRecords($xp),
            'addresses'      => $this->addresses($xp),
        ];
    }

    /* --------------------------------------------------------------------- */

    private function text(?DOMNode $n): string
    {
        if (! $n) return '';
        return trim(preg_replace('/\s+/', ' ', $n->textContent));
    }

    private function consumer(DOMXPath $xp): array
    {
        $name = '';
        foreach (["//*[@id='personNameTemplate']", "//*[contains(@class,'consumerName')]", "//*[@id='reportTop']//h1"] as $q) {
            $n = $xp->query($q)->item(0);
            if ($n && $this->text($n) !== '') { $name = $this->text($n); break; }
        }

        $date = null;
        // report date usually near the top; look for a mm/dd/yyyy
        $top = $this->text($xp->query('//body')->item(0) ? null : null);
        foreach ($xp->query("//*[@id='reportTop']|//*[contains(@class,'rpt_fullReport_header')]") as $n) {
            if (preg_match('#\b(\d{1,2}/\d{1,2}/\d{4})\b#', $this->text($n), $m)) { $date = $m[1]; break; }
        }

        return ['name' => $name ?: null, 'report_date' => $date];
    }

    /* ------------------------------ accounts ------------------------------ */

    private function accounts(DOMXPath $xp): array
    {
        $out = [];
        foreach ($xp->query("//table[contains(@class,'rpt_content_table')]") as $tbl) {
            /** @var DOMElement $tbl */
            $txt = $tbl->textContent;
            if (! preg_match('/Account\s*#|Account Type|Payment Status|Account Status/i', $txt)) {
                continue;                       // not an account table (contacts/summary/etc.)
            }

            // Map header column index -> bureau
            $cols = $this->bureauColumns($xp, $tbl);
            if (! $cols) continue;

            $bureaus = array_fill_keys(self::BUREAUS, []);
            foreach ($xp->query('.//tr', $tbl) as $tr) {
                $cells = $xp->query('./td|./th', $tr);
                if ($cells->length < 2) continue;
                $label = rtrim($this->text($cells->item(0)), ':');
                if ($label === '' || mb_strlen($label) > 40) continue;

                foreach ($cols as $idx => $bureau) {
                    $cell = $cells->item($idx);
                    if (! $cell) continue;
                    $val = $this->text($cell);
                    if ($val !== '' && $val !== '-') {
                        $bureaus[$bureau][$this->fieldKey($label)] = $val;
                    }
                }
            }

            // Drop bureaus with no data at all
            $bureaus = array_filter($bureaus, fn ($f) => ! empty($f));
            if (! $bureaus) continue;

            $creditor = $this->accountCreditor($xp, $tbl);
            $acctNo   = '';
            foreach ($bureaus as $f) { if (! empty($f['account_number'])) { $acctNo = $f['account_number']; break; } }

            $out[] = [
                'creditor'       => $creditor,
                'account_number' => $acctNo ?: null,
                'bureaus'        => $bureaus,
                'history'        => $this->paymentHistory($xp, $tbl),
            ];
        }

        return $out;
    }

    /** header th/td text -> [colIndex => bureauName] */
    private function bureauColumns(DOMXPath $xp, DOMElement $tbl): array
    {
        $map = [];
        // the header row is the first tr whose cells name the bureaus
        foreach ($xp->query('.//tr', $tbl) as $tr) {
            $cells = $xp->query('./td|./th', $tr);
            $found = [];
            for ($i = 0; $i < $cells->length; $i++) {
                foreach (self::BUREAUS as $b) {
                    if (stripos($this->text($cells->item($i)), $b) !== false) { $found[$i] = $b; }
                }
            }
            if (count($found) >= 2) { $map = $found; break; }
        }
        return $map;
    }

    private function fieldKey(string $label): string
    {
        $label = strtolower($label);
        return match (true) {
            str_contains($label, 'account #')            => 'account_number',
            str_contains($label, 'account type - detail') => 'account_type_detail',
            str_contains($label, 'account type')         => 'account_type',
            str_contains($label, 'bureau code')          => 'responsibility',
            str_contains($label, 'account status')       => 'account_status',
            str_contains($label, 'monthly payment')      => 'monthly_payment',
            str_contains($label, 'date opened')          => 'date_opened',
            str_contains($label, 'balance')              => 'balance',
            str_contains($label, 'terms') || str_contains($label, 'no. of months') => 'terms',
            str_contains($label, 'high credit')          => 'high_credit',
            str_contains($label, 'credit limit')         => 'credit_limit',
            str_contains($label, 'past due')             => 'past_due',
            str_contains($label, 'payment status')       => 'payment_status',
            str_contains($label, 'last reported')        => 'last_reported',
            str_contains($label, 'comments')             => 'comments',
            str_contains($label, 'date last active')     => 'date_last_active',
            str_contains($label, 'date of last payment') => 'date_last_payment',
            default                                      => preg_replace('/[^a-z0-9]+/', '_', $label),
        };
    }

    /** Best-effort creditor name: nearest preceding sub_header / heading text. */
    private function accountCreditor(DOMXPath $xp, DOMElement $tbl): ?string
    {
        // walk up to the account block, then find its header text
        $node = $tbl;
        for ($i = 0; $i < 4 && $node; $i++) {
            $prev = $node->previousSibling;
            while ($prev) {
                if ($prev->nodeType === XML_ELEMENT_NODE) {
                    $t = $this->text($prev);
                    if ($t !== '' && mb_strlen($t) <= 60 && ! preg_match('/TransUnion|Experian|Equifax/i', $t)) {
                        return $t;
                    }
                }
                $prev = $prev->previousSibling;
            }
            $node = $node->parentNode;
        }
        return null;
    }

    /** Count late marks per bureau in the account's 2-year payment grid. */
    private function paymentHistory(DOMXPath $xp, DOMElement $tbl): array
    {
        $out = [];
        // history cells sit in the same account block; scan following siblings
        // of the table's container for hstry-* classed cells grouped by bureau css.
        $container = $tbl->parentNode;
        if (! $container instanceof DOMElement) return $out;

        foreach (['TransUnion' => 'tuc', 'Experian' => 'exp', 'Equifax' => 'eqf'] as $bureau => $css) {
            $late = 0;
            $q = ".//*[contains(@class,'history.$css.css') or contains(@class,'hstry')]";
            foreach ($xp->query($q, $container) as $cell) {
                $class = $cell->getAttribute('class');
                foreach (self::LATE_CLASSES as $lc) {
                    if (str_contains($class, $lc)) { $late++; }
                }
            }
            if ($late > 0) $out[$bureau] = ['late' => $late];
        }
        return $out;
    }

    /* ----------------------------- inquiries ------------------------------ */

    private function inquiries(DOMXPath $xp): array
    {
        $out = [];
        $sec = $xp->query("//*[@id='Inquiries']")->item(0);
        if (! $sec) return $out;

        foreach ($xp->query('.//tr', $sec) as $tr) {
            $cells = $xp->query('./td', $tr);
            if ($cells->length < 4) continue;
            $creditor = $this->text($cells->item(0));
            $business = $this->text($cells->item(1));
            $date     = $this->text($cells->item(2));
            $bureau   = $this->text($cells->item(3));
            // skip the header row
            if (stripos($creditor, 'Creditor') !== false) continue;
            if ($creditor === '') continue;
            $out[] = compact('creditor', 'business', 'date', 'bureau');
        }
        return $out;
    }

    /* --------------------------- public records --------------------------- */

    private function publicRecords(DOMXPath $xp): array
    {
        $out = [];
        $sec = $xp->query("//*[@id='PublicInformation']")->item(0);
        if (! $sec) return $out;

        foreach ($xp->query('.//table', $sec) as $tbl) {
            $t = $this->text($tbl);
            if ($t === '' || mb_strlen($t) < 8) continue;
            $type = 'public record';
            if (stripos($t, 'bankrupt') !== false) $type = 'bankruptcy';
            $out[] = ['type' => $type, 'text_len' => mb_strlen($t), 'raw' => $this->text($tbl)];
        }
        return $out;
    }

    /* ------------------------------ addresses ----------------------------- */

    private function addresses(DOMXPath $xp): array
    {
        $out = [];
        $seen = [];
        foreach ($xp->query("//*[contains(@class,'addr_hsrty')]") as $n) {
            $line = $this->text($n);
            if ($line === '' || mb_strlen($line) < 6) continue;
            if (isset($seen[$line])) continue;
            $seen[$line] = true;
            $out[] = ['line' => $line];
        }
        return $out;
    }
}
