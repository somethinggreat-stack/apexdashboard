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
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'intake_monitoring_provider')) {
                $table->string('intake_monitoring_provider')->nullable()->after('intake_enabled');
            }
            if (!Schema::hasColumn('clients', 'intake_monitoring_enroll_url')) {
                $table->string('intake_monitoring_enroll_url')->nullable()->after('intake_monitoring_provider');
            }
        });

        // Enable the intake form for Chantal, with a fixed MyFreeScoreNow
        // provider + enroll link.
        DB::table('clients')->where('business_name', 'Chantal')->update([
            'intake_enabled'               => true,
            'intake_monitoring_provider'   => 'MyFreeScoreNow',
            'intake_monitoring_enroll_url' => 'https://app.myfreescorenow.com/enroll/B01C6910',
        ]);

        foreach (DB::table('clients')->where('business_name', 'Chantal')->whereNull('intake_token')->pluck('id') as $id) {
            DB::table('clients')->where('id', $id)->update(['intake_token' => Str::random(48)]);
        }
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['intake_monitoring_provider', 'intake_monitoring_enroll_url']);
        });
    }
};
