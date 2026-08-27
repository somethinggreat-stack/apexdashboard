<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The team's "work day" (shift) — the single source of truth for every feature
 * that counts a day's work, so they all agree (Daily Task, CFPB Logins, EOD,
 * Tasks View, nightly digest).
 *
 * The team works ~4 PM → 10 AM Pakistan time. The work-day rolls over at 4 PM
 * PKT — exactly when the new shift begins — so a full shift is one work-day,
 * a shift that runs 4 PM Aug 26 → 10 AM Aug 27 is labeled "Aug 26" (the night
 * it started), and during the 10 AM–4 PM off-gap the "current" shift stays the
 * one that just ended (so you can review / send its EOD). Timestamps are stored
 * UTC; these helpers map to/from the shift window.
 */
class WorkDay
{
    /** Pakistan Standard Time — UTC+5, no daylight saving. */
    public const TZ = 'Asia/Karachi';

    /** Day rollover hour in PKT (4 PM) — the moment a new shift starts. */
    public const ROLLOVER_HOUR = 16;

    /** The work-day date (Y-m-d) a given instant belongs to (defaults to now). */
    public static function dateFor(?Carbon $t = null): string
    {
        $p = ($t ? $t->copy() : Carbon::now())->timezone(self::TZ);
        if ($p->hour < self::ROLLOVER_HOUR) {
            $p = $p->subDay();
        }
        return $p->toDateString();
    }

    /** Today's (current, in-progress) work-day date. */
    public static function current(): string
    {
        return self::dateFor(Carbon::now());
    }

    /** The most recently COMPLETED work-day (whose window has fully ended). */
    public static function lastCompleted(): string
    {
        return Carbon::parse(self::current(), self::TZ)->subDay()->toDateString();
    }

    /**
     * Half-open [startUtc, endUtc) bounds for a work-day date. Query with
     * `created_at >= start AND created_at < end`.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function bounds(string $date): array
    {
        $start = Carbon::parse($date, self::TZ)->setTime(self::ROLLOVER_HOUR, 0, 0);
        return [$start->copy()->utc(), $start->copy()->addDay()->utc()];
    }

    /** The last $n work-day dates, newest first (for a date picker). */
    public static function recent(int $n = 15): array
    {
        $today = Carbon::parse(self::current(), self::TZ);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = $today->copy()->subDays($i)->toDateString();
        }
        return $out;
    }

    /** Normalise a requested date to a valid work-day string, or the current one. */
    public static function normalise(?string $date): string
    {
        if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            try {
                return Carbon::parse($date, self::TZ)->toDateString();
            } catch (\Throwable) {
                // fall through
            }
        }
        return self::current();
    }

    /** Human label for a work-day, e.g. "Wed, Aug 26, 2026". */
    public static function label(string $date): string
    {
        return Carbon::parse($date, self::TZ)->format('D, M j, Y');
    }

    /** Is this work-day the current (in-progress) shift? */
    public static function isCurrent(string $date): bool
    {
        return $date === self::current();
    }
}
