<?php

namespace App\Support;

use App\Models\Client;
use App\Models\EndUser;

/**
 * The Recycle Bin: deleted business owners and clients live here for a fixed
 * window, then are purged for good (rows + files). One place owns the retention
 * rule and the purge, shared by the scheduled command and the page's own
 * self-clean so it works with or without cron.
 */
class RecycleBin
{
    /** How long a deleted item is recoverable before it is purged for good. */
    public const RETENTION_DAYS = 10;

    /**
     * Permanently remove everything binned longer than RETENTION_DAYS.
     * Business owners go first (force-delete cascades to the clients binned
     * with them, taking their files), then any individually-deleted clients.
     *
     * @return array{owners:int, clients:int}
     */
    public static function purgeExpired(): array
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);

        $owners = 0;
        Client::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->get()
            ->each(function (Client $c) use (&$owners) {
                $c->forceDelete();   // cascades to its binned clients + files
                $owners++;
            });

        // Whatever trashed clients remain past the window are ones deleted on
        // their own (their business owner is still live) — purge those too.
        $clients = 0;
        EndUser::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->get()
            ->each(function (EndUser $u) use (&$clients) {
                $u->forceDelete();   // removes documents + identity files
                $clients++;
            });

        return ['owners' => $owners, 'clients' => $clients];
    }

    /** Whole days a binned item has left before it is purged (never below 0). */
    public static function daysLeft(?\DateTimeInterface $deletedAt): int
    {
        if (! $deletedAt) {
            return self::RETENTION_DAYS;
        }
        $purgeAt = \Illuminate\Support\Carbon::parse($deletedAt)->addDays(self::RETENTION_DAYS);
        return max(0, (int) ceil(now()->diffInHours($purgeAt, false) / 24));
    }
}
