<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospect_leads', function (Blueprint $table) {
            // Every lead is a "Hot Lead" by default; can be toggled off.
            $table->boolean('hot_lead')->default(true)->after('instagram');
        });
    }

    public function down(): void
    {
        Schema::table('prospect_leads', function (Blueprint $table) {
            $table->dropColumn('hot_lead');
        });
    }
};
