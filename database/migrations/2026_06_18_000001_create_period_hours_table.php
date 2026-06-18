<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One manually-entered total of hours per hourly BO pay period.
        // Periods are anchored to the client's pay_cycle_anchor (cycle start date).
        Schema::create('period_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('hours', 7, 2)->default(0);
            $table->timestamps();

            $table->unique(['client_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_hours');
    }
};
