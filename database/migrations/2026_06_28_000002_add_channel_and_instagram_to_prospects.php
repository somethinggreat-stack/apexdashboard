<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            // Pipeline channel: whatsapp | phone | instagram. Existing prospects
            // are all WhatsApp. `whatsapp` holds the contact number for the
            // whatsapp/phone channels; `instagram` is the handle for the
            // instagram channel (and is carried over from instagram leads).
            $table->string('channel', 20)->default('whatsapp')->index()->after('admin_id');
            $table->string('instagram')->nullable()->after('outreach_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn(['channel', 'instagram']);
        });
    }
};
