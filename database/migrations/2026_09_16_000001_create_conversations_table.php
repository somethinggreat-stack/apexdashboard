<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A conversation is a thread of team messages — either a 1:1 (type "dm") or a
 * named group (type "group"). Org-scoped by data_owner_id (the super admin's id),
 * exactly like every other team-chat concept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16)->default('dm');      // dm | group
            $table->string('name')->nullable();             // groups only
            $table->string('icon', 32)->nullable();         // group emoji/icon
            $table->unsignedBigInteger('data_owner_id');
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['data_owner_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
