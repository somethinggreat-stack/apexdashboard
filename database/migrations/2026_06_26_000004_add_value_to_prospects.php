<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            // Estimated deal value, used for the pipeline board's per-stage
            // totals (GHL-style). Optional — null is treated as $0.
            $table->decimal('value', 12, 2)->nullable()->after('referred_by');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn('value');
        });
    }
};
