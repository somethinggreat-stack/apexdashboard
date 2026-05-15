<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->enum('sender_type', ['admin', 'client', 'system'])->index();
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->text('body');
            $table->timestamp('admin_read_at')->nullable();
            $table->timestamp('client_read_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
