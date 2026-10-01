<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-VA access to the Numbers page, the same shape as can_manage_credentials
 * and can_manage_mailboxes: off for everyone, granted one VA at a time from the
 * Users page. A super admin never needs the flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->boolean('can_manage_numbers')->default(false)->after('can_manage_mailboxes');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('can_manage_numbers');
        });
    }
};
