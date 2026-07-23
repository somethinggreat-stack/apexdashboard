<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recycle Bin: soft-delete business owners (clients) and individual clients
 * (end_users) instead of destroying them. A deleted row keeps all its data and
 * files, is hidden everywhere by the SoftDeletes global scope, and can be
 * restored for 10 days before it is purged for good.
 *
 *  - deleted_at         : SoftDeletes marker (null = live).
 *  - deleted_by_admin_id: who sent it to the bin (super admin or VA).
 *  - deleted_with_owner  : (end_users only) true when the client was trashed as
 *    part of deleting its business owner, so restoring the owner brings it back
 *    but an individually-deleted client is left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by_admin_id')->nullable()->after('deleted_at');
            $table->index('deleted_at');
        });

        Schema::table('end_users', function (Blueprint $table) {
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by_admin_id')->nullable()->after('deleted_at');
            $table->boolean('deleted_with_owner')->default(false)->after('deleted_by_admin_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropColumn(['deleted_at', 'deleted_by_admin_id']);
        });

        Schema::table('end_users', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropColumn(['deleted_at', 'deleted_by_admin_id', 'deleted_with_owner']);
        });
    }
};
