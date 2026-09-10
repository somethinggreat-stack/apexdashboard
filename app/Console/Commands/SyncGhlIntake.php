<?php

namespace App\Console\Commands;

use App\Services\Ghl\GhlIntakeSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Imports Benny's GoHighLevel onboarding submissions. Runs on the scheduler,
 * and is safe to run by hand at any time — already-imported submissions are
 * skipped by ghl_submission_id.
 */
class SyncGhlIntake extends Command
{
    protected $signature = 'ghl:sync-intake {--dry-run : Report what would be imported without writing anything}';

    protected $description = 'Import Credit Repair onboarding submissions from GoHighLevel into New Clients';

    public function handle(): int
    {
        $sync = GhlIntakeSync::fromConfig();

        if (!$sync->isConfigured()) {
            // Not an error: the sync is simply off until the GHL_* values are set.
            $this->warn('GHL sync is not configured (GHL_API_KEY, GHL_LOCATION_ID, GHL_CREDIT_REPAIR_SURVEY_ID, GHL_SYNC_CLIENT_ID). Nothing to do.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $sync->run($dryRun);

        $summary = sprintf(
            '%simported %d, linked %d, skipped %d, failed %d',
            $dryRun ? '[dry run] would have ' : '',
            $result['imported'],
            $result['linked'],
            $result['skipped'],
            $result['failed'],
        );

        $this->info('GHL intake sync: ' . $summary);

        foreach ($result['errors'] as $error) {
            $this->error('  ' . $error);
        }

        // A silent failure here is how a paying client ends up waiting a week,
        // so anything that did not import is written to the log loudly.
        if ($result['failed'] > 0) {
            Log::error('GHL intake sync finished with failures', $result);

            return self::FAILURE;
        }

        if ($result['imported'] > 0) {
            Log::info('GHL intake sync: ' . $summary);
        }

        return self::SUCCESS;
    }
}
