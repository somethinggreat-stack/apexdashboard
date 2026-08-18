<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Non-payment access wall. When a business owner hasn't paid, the super admin
 * can revoke their dashboard access — they're then blocked at login and shown
 * their outstanding balance instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('access_revoked')->default(false)->after('status');
            $table->string('access_revoked_message', 500)->nullable()->after('access_revoked');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['access_revoked', 'access_revoked_message']);
        });
    }
};
