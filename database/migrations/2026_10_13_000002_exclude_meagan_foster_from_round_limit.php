<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Meagan Foster (621) is on Skool with UNLIMITED rounds until she stops
 * paying, so she must never carry a round limit. The round-limit migration no
 * longer seeds her, but if it already ran with her in the list, this clears
 * her limit and the system flag it wrote.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('end_users')->where('id', 621)->update(['round_limit' => null]);
        DB::table('client_events')->where('end_user_id', 621)
            ->where('event', 'round_limit')->whereNull('admin_id')->delete();
    }

    public function down(): void
    {
        // Nothing to restore — she is never on a round limit.
    }
};
