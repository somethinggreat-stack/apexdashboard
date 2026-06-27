<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospect_leads', function (Blueprint $table) {
            // Lead channel: whatsapp | phone | instagram. Existing leads are
            // all WhatsApp leads. The `whatsapp` column doubles as the contact
            // number for both the whatsapp and phone channels.
            $table->string('channel', 20)->default('whatsapp')->index()->after('admin_id');
        });
    }

    public function down(): void
    {
        Schema::table('prospect_leads', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
