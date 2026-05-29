<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BO payment configuration columns
        Schema::table('clients', function (Blueprint $table) {
            $table->string('compensation_model', 20)->default('per_round')->after('monthly_fee');
            $table->decimal('per_round_fee', 8, 2)->nullable()->after('compensation_model');
            $table->decimal('hourly_rate', 8, 2)->nullable()->after('per_round_fee');
            $table->unsignedInteger('weekly_hours_target')->nullable()->after('hourly_rate');
            $table->string('pay_cycle', 20)->nullable()->after('weekly_hours_target');
            $table->date('pay_cycle_anchor')->nullable()->after('pay_cycle');
        });

        // Per-round payments (used when comp model = 'per_round')
        Schema::create('client_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('end_user_id')->constrained('end_users')->onDelete('cascade');
            $table->unsignedTinyInteger('round');
            $table->decimal('amount', 8, 2);
            $table->date('paid_at');
            $table->string('method', 50)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_admin_id')->constrained('admins');
            $table->timestamps();

            $table->unique(['end_user_id', 'round']);
            $table->index('paid_at');
        });

        // Hourly time entries (used when comp model = 'hourly')
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->date('work_date');
            $table->decimal('hours', 5, 2);
            $table->text('description')->nullable();
            $table->foreignId('created_by_admin_id')->constrained('admins');
            $table->timestamps();

            $table->index(['client_id', 'work_date']);
        });

        // Hourly payouts (recorded payments per pay period)
        Schema::create('time_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('hours_in_period', 7, 2);
            $table->decimal('amount_paid', 9, 2);
            $table->date('paid_at');
            $table->string('method', 50)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_admin_id')->constrained('admins');
            $table->timestamps();

            $table->unique(['client_id', 'period_start', 'period_end']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_payouts');
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('client_payments');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'compensation_model',
                'per_round_fee',
                'hourly_rate',
                'weekly_hours_target',
                'pay_cycle',
                'pay_cycle_anchor',
            ]);
        });
    }
};
