<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal team direct messages — one admin (super/VA) to another, within the
 * same org. A thread between two admins is simply the messages where they are
 * the sender/recipient in either direction. Read state is per-message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('admins')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('admins')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'read_at']);        // unread counts
            $table->index(['sender_id', 'recipient_id', 'id']); // thread lookups
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_messages');
    }
};
