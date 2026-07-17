<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money actually paid OUT to a referrer. Commission earned is derived live from
 * client_payments; this table only records the settlements against it, so we
 * can show earned / paid / outstanding per referrer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('referrer_name');
            $table->decimal('amount', 9, 2);
            $table->date('paid_at');
            $table->text('note')->nullable();
            $table->foreignId('created_by_admin_id')->constrained('admins');
            $table->timestamps();

            $table->index('referrer_name');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_payouts');
    }
};
