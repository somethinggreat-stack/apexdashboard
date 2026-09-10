<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When this person was pulled from GoHighLevel — which is not the same as when
 * they submitted the form, and not the same as created_at either, because a
 * record that already existed keeps its original creation date. This is what
 * the pull history on the GHL Clients screen is ordered by.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->timestamp('ghl_synced_at')->nullable()->after('ghl_submission_id');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn('ghl_synced_at');
        });
    }
};
