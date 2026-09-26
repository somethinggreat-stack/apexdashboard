<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Turn results tracking on for Genius Credit Boutique, the same way it was turned
 * on for Clinecea Phillips (2026_08_20_000001).
 *
 * `clients.results_tracking` is a single flag and it gates more than the "Sent for
 * Approval" button that prompted this. Switching it on for an owner also enables,
 * for that owner only:
 *
 *   - negative-item tracking at intake and on the client record
 *   - the EOD and monthly results reports
 *   - the round-approval flow (Sent for Approval / approve / clear)
 *   - and, in the BUSINESS OWNER'S OWN PORTAL, the results pages and the
 *     this-shift snapshot on their dashboard
 *
 * That last one is the part worth knowing: Genius Credit Boutique will see new
 * things when they next log in, not just Apex's VAs.
 *
 * Matched on the full name rather than a prefix so it cannot catch a future owner
 * whose name happens to begin the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('clients')
            ->where('business_name', 'like', 'Genius Credit Boutique%')
            ->update(['results_tracking' => true]);
    }

    public function down(): void
    {
        DB::table('clients')
            ->where('business_name', 'like', 'Genius Credit Boutique%')
            ->update(['results_tracking' => false]);
    }
};
