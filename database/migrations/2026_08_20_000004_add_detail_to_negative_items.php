<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A per-item detail line: the ACCOUNT NUMBER for a negative account, or the
 * INQUIRY DATE for an inquiry. Empty for personal information / employers /
 * bankruptcy (the name carries everything).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negative_items', function (Blueprint $table) {
            $table->string('detail')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('negative_items', function (Blueprint $table) {
            $table->dropColumn('detail');
        });
    }
};
