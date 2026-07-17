<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referral / commission fields on a business owner. When a BO is referred by
 * someone (referrer_name set), that referrer earns commission_per_payment for
 * every client payment recorded under that BO. Both null = not a referred BO.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('referrer_name')->nullable()->after('status');
            $table->decimal('commission_per_payment', 8, 2)->nullable()->after('referrer_name');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['referrer_name', 'commission_per_payment']);
        });
    }
};
