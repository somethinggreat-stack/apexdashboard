<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-business-owner custom lists (currently used only by Tycon Stan). A client
 * can be tagged into one custom list ("jumbo" / "mr_pierre" / "tycoon"), which
 * moves it out of the owner's normal buckets on THEIR portal only — the admin
 * and VA views ignore this column entirely.
 *
 *  - end_users.custom_list          : which custom list the client is in (null = none).
 *  - clients.custom_lists_enabled   : shows the custom-list nav + move buttons on that
 *                                     owner's portal. Off for everyone but Tycon Stan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->string('custom_list', 32)->nullable()->after('intake_status');
            $table->index(['client_id', 'custom_list']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('custom_lists_enabled')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'custom_list']);
            $table->dropColumn('custom_list');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('custom_lists_enabled');
        });
    }
};
