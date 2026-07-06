<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Clients that were "sent back" (have an error note) predate the Errors
        // bucket and are still sitting in New Clients as pending_review. Move
        // every one that carries an error note into the Errors bucket.
        DB::table('end_users')
            ->where('intake_status', 'pending_review')
            ->whereNotNull('intake_review_note')
            ->update(['intake_status' => 'error']);
    }

    public function down(): void
    {
        // No-op: we can't safely tell which error clients were originally
        // pending vs. genuinely new, so we don't reverse this.
    }
};
