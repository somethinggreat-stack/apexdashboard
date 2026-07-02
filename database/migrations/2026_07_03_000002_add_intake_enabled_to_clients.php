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
            if (!Schema::hasColumn('clients', 'intake_enabled')) {
                $table->boolean('intake_enabled')->default(false)->after('intake_token');
            }
        });

        // Enable the public intake form only for Tishia Rolon, and make sure
        // she has a secret token to build the link from.
        DB::table('clients')->where('business_name', 'Tishia Rolon')->update(['intake_enabled' => true]);

        foreach (DB::table('clients')->where('business_name', 'Tishia Rolon')->whereNull('intake_token')->pluck('id') as $id) {
            DB::table('clients')->where('id', $id)->update(['intake_token' => Str::random(48)]);
        }
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('intake_enabled');
        });
    }
};
