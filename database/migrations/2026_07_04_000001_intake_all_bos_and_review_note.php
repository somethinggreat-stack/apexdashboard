<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Every business owner gets the hosted intake link + New Clients.
        DB::table('clients')->update(['intake_enabled' => true]);

        foreach (DB::table('clients')->whereNull('intake_token')->pluck('id') as $id) {
            DB::table('clients')->where('id', $id)->update(['intake_token' => Str::random(48)]);
        }

        // A VA can bounce a client back to New Clients with a reason to fix.
        Schema::table('end_users', function (Blueprint $table) {
            if (!Schema::hasColumn('end_users', 'intake_review_note')) {
                $table->text('intake_review_note')->nullable()->after('intake_submitted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn('intake_review_note');
        });
    }
};
