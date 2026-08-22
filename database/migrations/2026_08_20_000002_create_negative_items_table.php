<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-client negative accounts/items. Entered when a client is added and marked
 * off as they resolve. Powers the monthly results report: an item's opened_on +
 * resolved_at + status is all the monthly deltas are computed from.
 *
 *  - goal:   'delete'  (letters to remove it) | 'update' (negative -> positive)
 *  - status: 'reporting' (still on file) | 'deleted' | 'updated' (to positive)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('negative_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('end_user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->default('negative_account');   // negative_account|inquiry|bankruptcy
            $table->string('goal')->default('delete');        // delete|update
            $table->string('bureau')->default('all');          // all|experian|transunion|equifax
            $table->string('status')->default('reporting');   // reporting|deleted|updated
            $table->date('opened_on');                        // when it entered the file (defaults to client start)
            $table->date('resolved_at')->nullable();          // when deleted/updated
            $table->unsignedInteger('resolved_round')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps();

            $table->index('end_user_id');
            $table->index('status');
            $table->index('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('negative_items');
    }
};
