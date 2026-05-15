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
        // Step 1: add columns nullable
        Schema::table('clients', function (Blueprint $table) {
            $table->string('intake_token', 64)->nullable()->after('id');
            $table->string('intake_logo_path')->nullable();
            $table->string('intake_display_name', 255)->nullable();
        });

        // Step 2: backfill tokens for existing rows
        DB::table('clients')->whereNull('intake_token')->orderBy('id')->each(function ($row) {
            DB::table('clients')->where('id', $row->id)->update([
                'intake_token' => Str::random(48),
            ]);
        });

        // Step 3: add unique constraint now that all rows have a value
        Schema::table('clients', function (Blueprint $table) {
            $table->unique('intake_token');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['intake_token']);
            $table->dropColumn(['intake_token', 'intake_logo_path', 'intake_display_name']);
        });
    }
};
