<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advance payments: money a business owner paid up front, before the rounds it
 * covers were done. Each advance is a ledger row; the owner's credit balance is
 * the advances minus the round payments taken from them (client_payments rows
 * with from_advance = true). Applying credit writes ordinary round payments, so
 * revenue and commission count the money once — when it pays for a round.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('received_at');
            $table->string('method', 50)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index('client_id');
        });

        Schema::table('client_payments', function (Blueprint $table) {
            $table->boolean('from_advance')->default(false)->after('is_free');
        });
    }

    public function down(): void
    {
        Schema::table('client_payments', function (Blueprint $table) {
            $table->dropColumn('from_advance');
        });

        Schema::dropIfExists('owner_advances');
    }
};
