<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who is in a conversation, plus their per-person state: role (group admin vs
 * member), the read watermark (last_read_message_id), and mute/favorite flags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('role', 16)->default('member');   // member | admin
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->boolean('muted')->default(false);
            $table->boolean('favorite')->default(false);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'admin_id']);
            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
    }
};
