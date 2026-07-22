<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round Errors bucket. A client already past round 1 (in the Clients list) can
 * be pulled out with a round-import problem: intake_status = 'round_error',
 * with a free-text error_type and the reason kept in intake_review_note.
 * Resolving sends them back to the Clients list (intake_status = 'done').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            if (!Schema::hasColumn('end_users', 'error_type')) {
                $table->string('error_type')->nullable()->after('intake_review_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn('error_type');
        });
    }
};
