<?php

namespace App\Console\Commands;

use App\Models\EndUser;
use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoPauseInactive extends Command
{
    protected $signature = 'clients:auto-pause {--days=14 : days of inactivity before auto-pause} {--dry-run : just report, do not change anything}';
    protected $description = 'Pause active clients who have not had a process step logged in N+ days.';

    public function handle(): int
    {
        $days   = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days)->toDateString();

        // Candidates: active end_users whose most-recent process step (if any) is older than the cutoff,
        // AND whose start_date is also older than the cutoff (don't pause brand new ones).
        $candidates = EndUser::query()
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $cutoff)
            ->whereNotExists(function ($q) use ($cutoff) {
                $q->select(DB::raw(1))
                  ->from('process_steps')
                  ->whereColumn('process_steps.end_user_id', 'end_users.id')
                  ->whereDate('process_steps.step_date', '>', $cutoff);
            })
            ->get();

        if ($candidates->isEmpty()) {
            $this->info("No active clients are inactive for {$days}+ days. Nothing to do.");
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Found {$candidates->count()} client(s) inactive for {$days}+ days:");

        foreach ($candidates as $eu) {
            $this->line("  · #{$eu->id} {$eu->full_name} (BO {$eu->client_id}, started {$eu->start_date?->toDateString()})");

            if ($dryRun) continue;

            $eu->update(['status' => 'paused']);

            Message::postSystem(
                $eu->client_id,
                "Auto-paused: {$eu->full_name} had no activity for {$days}+ days. Review their file and resume when ready."
            );
        }

        return self::SUCCESS;
    }
}
