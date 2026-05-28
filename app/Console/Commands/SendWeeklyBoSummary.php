<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ProcessStep;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendWeeklyBoSummary extends Command
{
    protected $signature = 'bo:weekly-summary {--dry-run : print to console instead of emailing}';
    protected $description = 'For each business owner, email a summary of what was done across their clients in the last 7 days.';

    public function handle(): int
    {
        $since  = now()->subDays(7);
        $dryRun = (bool) $this->option('dry-run');
        $sent   = 0;

        $bos = Client::with(['endUsers' => function ($q) use ($since) {
            $q->withCount(['processSteps as recent_steps_count' => fn ($q2) => $q2->where('step_date', '>=', $since)]);
        }])->get();

        foreach ($bos as $bo) {
            $clientsTouched = $bo->endUsers->filter(fn ($eu) => ($eu->recent_steps_count ?? 0) > 0);
            $totalSteps     = $clientsTouched->sum('recent_steps_count');

            if ($totalSteps === 0) {
                $this->line("Skipping {$bo->business_name}: no activity in the last 7 days.");
                continue;
            }

            $body  = "Hi {$bo->business_name},\n\n";
            $body .= "Here's what our team did for your clients in the last 7 days:\n\n";

            foreach ($clientsTouched as $eu) {
                $steps = ProcessStep::where('end_user_id', $eu->id)
                    ->where('step_date', '>=', $since)
                    ->orderBy('step_date')
                    ->get();

                $body .= "• {$eu->full_name}\n";
                foreach ($steps as $s) {
                    $body .= "    - " . $s->step_date?->format('M d') . ": Round {$s->round}, Week {$s->week} — " . ($s->step_type_label ?? $s->step_type) . "\n";
                }
                $body .= "\n";
            }

            $body .= "Total: {$totalSteps} step(s) logged across {$clientsTouched->count()} client(s).\n";

            if ($dryRun) {
                $this->info("===== Summary for {$bo->business_name} ({$bo->email}) =====");
                $this->line($body);
                continue;
            }

            try {
                Mail::raw($body, function ($m) use ($bo) {
                    $m->to($bo->email)
                      ->subject('Weekly progress summary — your clients');
                });
                $sent++;
                $this->info("Sent summary to {$bo->business_name} <{$bo->email}>");
            } catch (\Throwable $e) {
                Log::error("Weekly BO summary failed for {$bo->business_name}: " . $e->getMessage());
                $this->error("FAILED for {$bo->business_name}: " . $e->getMessage());
            }
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Done. {$sent} email(s) sent.");
        return self::SUCCESS;
    }
}
