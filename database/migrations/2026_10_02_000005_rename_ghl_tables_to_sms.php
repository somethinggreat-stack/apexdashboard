<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The number pool moved off GoHighLevel onto a provider that pushes inbound SMS
 * to a webhook, so the tables lose their GHL-shaped names and columns.
 *
 * The conversation columns go with them: there is no second inbox any more. The
 * message is delivered here and nowhere else, so nothing has to be deleted
 * afterwards — which also retires the one genuinely dangerous part of the old
 * design, deleting threads that sat beside real customer conversations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('ghl_numbers', 'sms_numbers');
        Schema::rename('ghl_otps', 'sms_codes');

        Schema::table('sms_numbers', function (Blueprint $table) {
            $table->dropColumn('ghl_sid');
        });

        Schema::table('sms_codes', function (Blueprint $table) {
            // The index has to go before the column it covers: SQLite refuses
            // to drop a column an index still points at, and MySQL would leave
            // a stale index behind.
            $table->dropIndex('ghl_otps_conversation_id_index');
        });

        Schema::table('sms_codes', function (Blueprint $table) {
            $table->dropColumn(['conversation_id', 'deleted_from_ghl', 'delete_note']);
        });

        Schema::table('sms_codes', function (Blueprint $table) {
            $table->renameColumn('ghl_number_id', 'sms_number_id');
            // The provider's own id for the message: what makes a retried
            // webhook record one row instead of two.
            $table->renameColumn('message_id', 'provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('sms_codes', function (Blueprint $table) {
            $table->renameColumn('sms_number_id', 'ghl_number_id');
            $table->renameColumn('provider_message_id', 'message_id');
        });

        Schema::table('sms_codes', function (Blueprint $table) {
            $table->string('conversation_id', 64)->nullable();
            $table->boolean('deleted_from_ghl')->default(false);
            $table->string('delete_note', 191)->nullable();
        });

        Schema::table('sms_codes', function (Blueprint $table) {
            $table->index('conversation_id', 'ghl_otps_conversation_id_index');
        });

        Schema::table('sms_numbers', function (Blueprint $table) {
            $table->string('ghl_sid', 64)->nullable();
        });

        Schema::rename('sms_codes', 'ghl_otps');
        Schema::rename('sms_numbers', 'ghl_numbers');
    }
};
