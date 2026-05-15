<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('end_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('ssn')->nullable();
            $table->string('photo_id_path')->nullable();
            $table->string('proof_of_address_path')->nullable();
            $table->string('credit_monitoring_name')->nullable();
            $table->string('credit_monitoring_username')->nullable();
            $table->text('credit_monitoring_password')->nullable();
            $table->integer('current_score')->nullable();
            $table->integer('goal_score')->default(700);
            $table->enum('status', ['active', 'paused', 'graduated', 'cancelled'])->default('active');
            $table->date('start_date');
            $table->timestamps();

            $table->index('client_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('end_users');
    }
};
