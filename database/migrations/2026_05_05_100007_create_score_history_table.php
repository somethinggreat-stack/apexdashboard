<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('end_user_id')->constrained('end_users')->onDelete('cascade');
            $table->integer('score');
            $table->enum('bureau', ['experian', 'equifax', 'transunion', 'average']);
            $table->date('recorded_at');
            $table->timestamps();

            $table->index('end_user_id');
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_history');
    }
};
