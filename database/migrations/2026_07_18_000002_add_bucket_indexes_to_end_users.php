<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every page load runs several bucket counts for the sidebar badges and lists
 * (New Clients / Errors / In Progress / Clients / Hold), all filtering on
 * client_id + intake_status and client_id + held_at. Only client_id was
 * indexed, so those counts scanned every row for the owner. These composite
 * indexes let them hit the index directly. Pure speed — no behaviour change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            try {
                $table->index(['client_id', 'intake_status'], 'end_users_client_intake_idx');
            } catch (\Throwable $e) {
                // already present — nothing to do
            }
        });

        Schema::table('end_users', function (Blueprint $table) {
            try {
                $table->index(['client_id', 'held_at'], 'end_users_client_held_idx');
            } catch (\Throwable $e) {
                // already present — nothing to do
            }
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            try { $table->dropIndex('end_users_client_intake_idx'); } catch (\Throwable $e) {}
            try { $table->dropIndex('end_users_client_held_idx'); } catch (\Throwable $e) {}
        });
    }
};
