<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            // 'super' sees everything (leads, payments, users). 'va' only works
            // on the business owners. Existing admins default to super.
            if (!Schema::hasColumn('admins', 'role')) {
                $table->string('role', 20)->default('super')->after('full_name');
            }
            // VAs share the super admin's data (business owners, clients).
            if (!Schema::hasColumn('admins', 'parent_admin_id')) {
                $table->unsignedBigInteger('parent_admin_id')->nullable()->index()->after('role');
            }
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable()->index();
            $table->string('action')->nullable();       // route name
            $table->text('description')->nullable();     // human-readable
            $table->string('method', 10)->nullable();
            $table->string('path')->nullable();
            $table->string('subject')->nullable();       // e.g. the BO being worked on
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['role', 'parent_admin_id']);
        });
    }
};
