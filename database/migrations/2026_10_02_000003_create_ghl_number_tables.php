<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shared pool of GHL phone numbers VAs claim to collect one-time codes,
 * and the record of every code that arrived.
 *
 * ghl_otps outlives the message itself: once the conversation is deleted from
 * GoHighLevel this row is the ONLY evidence a code ever existed — which number
 * it came to, who was holding it, and who copied it. It is the audit trail, so
 * nothing in the app deletes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ghl_numbers', function (Blueprint $table) {
            $table->id();

            $table->string('phone', 32)->unique();      // E.164, as GHL reports it
            $table->string('ghl_sid', 64)->nullable();  // the number's id in GHL
            $table->string('label', 120)->nullable();   // "Alvina's number 6"

            // Who is holding it right now. Null = free.
            $table->unsignedBigInteger('claimed_by_admin_id')->nullable()->index();
            $table->timestamp('claimed_at')->nullable();

            // A number can be taken out of the pool without deleting its history.
            $table->boolean('active')->default(true);

            $table->timestamps();

            // "What is free right now" is the only question this page asks often.
            $table->index(['active', 'claimed_by_admin_id']);
        });

        Schema::create('ghl_otps', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('ghl_number_id')->index();
            // The VA who held the number when it arrived.
            $table->unsignedBigInteger('claimed_by_admin_id')->nullable()->index();

            $table->string('from_number', 32)->nullable();   // who sent it
            $table->string('code', 16)->nullable();          // what we read out of it
            $table->text('body')->nullable();                // the whole message, always
            $table->timestamp('received_at')->nullable();

            // GHL's own ids, kept so a thread can be matched up again if needed.
            $table->string('conversation_id', 64)->nullable()->index();
            $table->string('message_id', 64)->nullable();

            $table->timestamp('copied_at')->nullable();
            // Whether the thread was removed from GHL afterwards, and if not, why.
            $table->boolean('deleted_from_ghl')->default(false);
            $table->string('delete_note', 191)->nullable();

            $table->timestamps();

            // The newest code for a number, which is what the page asks for.
            $table->index(['ghl_number_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ghl_otps');
        Schema::dropIfExists('ghl_numbers');
    }
};
