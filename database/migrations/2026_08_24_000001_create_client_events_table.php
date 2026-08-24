<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-client audit trail (super-admin only): every list move, hold/resume,
 * profile edit, and result change on an end user — who did it and when.
 * Complements the who/when already stored on process_steps, notes and
 * negative_items; the client Activity tab merges all of these into a timeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('end_user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('admin_id')->nullable();   // who did it (null = system)
            $table->string('event');                              // moved | held | resumed | created | profile | result
            $table->string('description');                        // human text, e.g. "Moved to Round Errors"
            $table->timestamps();

            $table->index(['end_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_events');
    }
};
