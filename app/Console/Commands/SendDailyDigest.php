<?php

namespace App\Console\Commands;

use App\Models\EndUser;
use App\Models\Message;
use App\Models\ProcessStep;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Nightly 10 PM ET summary to each business owner: the clients our team worked
 * today and which round we started for them — the same signal as the Tasks View
 * / Daily Task (a Round-N Week-1 step logged = a round started). One message per
 * owner, sent as "Apex Growth Team". Owners with no work today get nothing.
 */
class SendDailyDigest extends Command
{
    protected $signature = 'messages:daily-digest';

    protected $description = "Send each business owner a nightly summary of the clients worked today (rounds started).";

    private const TZ = 'America/New_York';

    public function handle(): int
    {
        $appTz     = config('app.timezone') ?: 'UTC';
        $startEt   = Carbon::now(self::TZ)->startOfDay();          // midnight ET today
        $startQ    = $startEt->copy()->setTimezone($appTz);        // same instant, DB timezone

        // Today's round-starts: Week-1 steps logged since midnight ET.
        $steps = ProcessStep::where('created_at', '>=', $startQ)
            ->where('week', 1)
            ->with('endUser')
            ->get();

        // client_id => [ euId-round => ['name' => , 'round' => int] ]
        $byOwner = [];
        foreach ($steps as $step) {
            $eu = $step->endUser;
            if (! $eu) {
                continue;
            }
            $byOwner[$eu->client_id][$eu->id . '-' . $step->round] = [
                'name'  => $eu->full_name,
                'round' => (int) $step->round,
            ];
        }

        $dateLabel = $startEt->format('M j');
        $sent = 0;

        foreach ($byOwner as $clientId => $entries) {
            // Sort by client name for a tidy list.
            uasort($entries, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

            $clients = count($entries);
            $parts   = array_map(
                fn ($e) => "{$e['name']} (Round {$e['round']} started)",
                array_values($entries)
            );

            $body = "Daily update — {$dateLabel}. Our team worked on {$clients} of your "
                . Str::plural('client', $clients) . " today: " . implode(', ', $parts) . '.';

            Message::postFromTeam((int) $clientId, $body);
            $sent++;
        }

        $this->info("Daily digest sent to {$sent} business owner(s).");

        return self::SUCCESS;
    }
}
