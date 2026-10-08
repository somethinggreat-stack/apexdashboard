<?php

namespace App\Services;

use App\Models\Client;
use App\Models\EndUser;
use App\Models\RoundSelection;
use App\Models\TimePayout;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Billing for the "Monthly Package" model (compensation_model = 'package'):
 * a fixed fee covers the first N rounds processed in a monthly period, and each
 * round past N costs the overage rate. A period with no rounds owes nothing.
 *
 * A round counts in the period it was FIRST selected for a client (the
 * round_selections signal the Tasks View uses) — and once marked, it stays
 * billed for that period whatever happens to the client afterwards: moved to
 * Hold, Round Errors, Sent for Approval, back to New Clients or New Client
 * Errors, or the round taken back off the strip. Only deleted clients drop out.
 *
 * Periods are calendar-month windows from the owner's pay_cycle_anchor, read in
 * Pakistan time — the team's working day. The VAs work the PKT night shift, so
 * in Eastern time one night's work straddles two dates and a month's batch
 * spills a day into the next period.
 */
class RoundPackageBilling
{
    public const TZ = 'Asia/Karachi';

    public function __construct(private readonly Client $client)
    {
    }

    public function fee(): float
    {
        return (float) ($this->client->package_fee ?? 0);
    }

    public function includedRounds(): int
    {
        return (int) ($this->client->package_rounds ?? 0);
    }

    public function overageFee(): float
    {
        return (float) ($this->client->package_overage_fee ?? 0);
    }

    /**
     * What a period with $rounds processed rounds costs.
     *
     * @return array{rounds:int, included:int, extra:int, fee:float, overage_rate:float, extra_amount:float, total:float}
     */
    public function price(int $rounds): array
    {
        $extra       = max(0, $rounds - $this->includedRounds());
        $fee         = $rounds > 0 ? $this->fee() : 0.0;
        $extraAmount = round($extra * $this->overageFee(), 2);

        return [
            'rounds'       => $rounds,
            'included'     => min($rounds, $this->includedRounds()),
            'extra'        => $extra,
            'fee'          => $fee,
            'overage_rate' => $this->overageFee(),
            'extra_amount' => $extraAmount,
            'total'        => round($fee + $extraAmount, 2),
        ];
    }

    /**
     * The rounds processed in [$start, $end] (whole days, Pakistan time), oldest first.
     *
     * @return Collection<int, array{end_user_id:int, name:string, round:int, processed_at:Carbon}>
     */
    public function roundsIn(Carbon $start, Carbon $end): Collection
    {
        $from = Carbon::parse($start->toDateString(), self::TZ)->startOfDay()->utc();
        $to   = Carbon::parse($end->toDateString(), self::TZ)->endOfDay()->utc();

        $clients = EndUser::forClient($this->client->id)
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'suffix'])
            ->keyBy('id');

        if ($clients->isEmpty()) {
            return collect();
        }

        return RoundSelection::whereIn('end_user_id', $clients->keys())
            ->selectRaw('end_user_id, round, MIN(created_at) AS first_at')
            ->groupBy('end_user_id', 'round')
            ->get()
            ->map(function ($row) use ($clients) {
                $eu = $clients->get($row->end_user_id);

                if (! $eu) {
                    return null;
                }

                return [
                    'end_user_id'  => (int) $row->end_user_id,
                    'name'         => $eu->full_name,
                    'round'        => (int) $row->round,
                    'processed_at' => Carbon::parse($row->first_at, 'UTC'),
                ];
            })
            ->filter(fn ($r) => $r && $r['processed_at']->between($from, $to))
            ->sortBy(fn ($r) => $r['processed_at']->getTimestamp())
            ->values();
    }

    /**
     * The current period and the ones before it (newest first, never before the
     * anchor), each with its rounds, price and any recorded payment.
     *
     * @return array<int, array{start:Carbon, end:Carbon, is_current:bool, rounds:Collection, price:array, payout:?TimePayout}>
     */
    public function periods(int $max = 6): array
    {
        [$start, $end] = $this->periodContaining(Carbon::now(self::TZ));

        $payouts = TimePayout::where('client_id', $this->client->id)->get()
            ->keyBy(fn ($p) => $p->period_start->toDateString());

        $out = [];
        while ($start && count($out) < $max) {
            $rounds = $this->roundsIn($start, $end);
            $out[] = [
                'start'      => $start->copy(),
                'end'        => $end->copy(),
                'is_current' => $out === [],
                'rounds'     => $rounds,
                'price'      => $this->price($rounds->count()),
                'payout'     => $payouts->get($start->toDateString()),
            ];
            [$start, $end] = $this->previousPeriod($start) ?? [null, null];
        }

        return $out;
    }

    /** Owed for every period (opening one included) that has rounds and no recorded payment. */
    public function outstanding(): float
    {
        return round(collect($this->periods(240))
            ->filter(fn ($p) => ! $p['payout'])
            ->sum(fn ($p) => $p['price']['total']), 2);
    }

    /**
     * The period a day falls in. Before the anchor that is the opening period —
     * from the day the owner was added up to the day before the anchor — so the
     * first, uneven stretch of work is billed as one period rather than lost.
     *
     * @return array{0:Carbon, 1:Carbon}
     */
    public function periodContaining(Carbon $day): array
    {
        $day   = Carbon::parse($day->toDateString(), self::TZ)->startOfDay();
        $start = $this->anchor();

        if ($day->lessThan($start) && ($opening = $this->openingPeriod())) {
            return $opening;
        }

        while ($start->copy()->addMonthNoOverflow()->lessThanOrEqualTo($day)) {
            $start->addMonthNoOverflow();
        }

        return [$start, $start->copy()->addMonthNoOverflow()->subDay()];
    }

    /** The period before $start, or null once there is nothing earlier to bill. */
    private function previousPeriod(Carbon $start): ?array
    {
        $anchor = $this->anchor();

        if ($start->greaterThan($anchor)) {
            return [$start->copy()->subMonthNoOverflow()->max($anchor), $start->copy()->subDay()];
        }

        $opening = $this->openingPeriod();

        return $opening && $start->equalTo($anchor) ? $opening : null;
    }

    /** From the day the owner was added to the day before the anchor, if that's any time at all. */
    private function openingPeriod(): ?array
    {
        $anchor = $this->anchor();
        $added  = $this->client->created_at
            ? Carbon::parse($this->client->created_at)->timezone(self::TZ)->startOfDay()
            : null;

        return $added && $added->lessThan($anchor)
            ? [$added, $anchor->copy()->subDay()]
            : null;
    }

    private function anchor(): Carbon
    {
        $anchor = $this->client->pay_cycle_anchor;

        return $anchor
            ? Carbon::parse($anchor->toDateString(), self::TZ)->startOfDay()
            : Carbon::now(self::TZ)->startOfMonth();
    }
}
