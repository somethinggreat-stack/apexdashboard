<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a client enters the Clients (Done) list. Powers the Daily Task report,
 * which lists — per business owner — clients newly added to the list in the
 * last 12 hours (alongside those that had process steps logged).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->timestamp('listed_at')->nullable()->after('start_date');
            $table->index('listed_at');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropIndex(['listed_at']);
            $table->dropColumn('listed_at');
        });
    }
};
