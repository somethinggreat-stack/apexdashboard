<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-VA switch for the business owner Credentials vault. Off by default — a VA
 * sees Credentials only once a super admin turns this on from Users & Activity.
 * Super admins always have access regardless of this flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->boolean('can_manage_credentials')->default(false)->after('parent_admin_id');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('can_manage_credentials');
        });
    }
};
