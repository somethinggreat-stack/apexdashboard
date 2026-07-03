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
            if (!Schema::hasColumn('clients', 'intake_api_key')) {
                $table->string('intake_api_key', 64)->nullable()->unique()->after('intake_enabled');
            }
            if (!Schema::hasColumn('clients', 'intake_external_url')) {
                $table->string('intake_external_url')->nullable()->after('intake_api_key');
            }
        });

        // Chantal receives intake via her own external form (Prestige) — give
        // her an API key and record the external form URL. Our hosted form is
        // disabled for her (the controller 404s when intake_external_url is set).
        foreach (DB::table('clients')->where('business_name', 'Chantal')->pluck('id') as $id) {
            DB::table('clients')->where('id', $id)->update([
                'intake_enabled'      => true,
                'intake_external_url' => 'https://prestigecreditconciergeservices.com/onboarding.html',
                'intake_api_key'      => 'ags_' . Str::random(48),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['intake_api_key', 'intake_external_url']);
        });
    }
};
