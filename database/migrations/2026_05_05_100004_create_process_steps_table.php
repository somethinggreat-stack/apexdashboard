<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('end_user_id')->constrained('end_users')->onDelete('cascade');
            $table->unsignedTinyInteger('round')->default(1);
            $table->unsignedTinyInteger('week')->default(1);
            $table->string('step_type');
            $table->date('step_date');
            // Per-bureau tracking — only filled on Week 4 / record_deletions
            $table->integer('experian_accounts_disputed')->nullable();
            $table->integer('experian_inquiries_disputed')->nullable();
            $table->integer('transunion_accounts_disputed')->nullable();
            $table->integer('transunion_inquiries_disputed')->nullable();
            $table->integer('equifax_accounts_disputed')->nullable();
            $table->integer('equifax_inquiries_disputed')->nullable();
            $table->integer('previous_credit_score')->nullable();
            $table->integer('credit_score_now')->nullable();
            $table->foreignId('created_by_admin_id')->constrained('admins');
            $table->timestamps();

            $table->index('end_user_id');
            $table->index('step_type');
            $table->index('step_date');
            $table->index(['round', 'week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_steps');
    }
};
