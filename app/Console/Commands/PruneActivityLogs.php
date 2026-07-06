<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

class PruneActivityLogs extends Command
{
    protected $signature = 'activity:prune {--days=7 : Delete activity logs older than this many days}';

    protected $description = 'Delete activity log entries older than the given number of days (default 7).';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days < 1) {
            $days = 7;
        }

        $cutoff  = now()->subDays($days);
        $deleted = ActivityLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} activity log(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
