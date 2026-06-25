<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            // WhatsApp number used to contact this prospect, and who referred
            // them (optional). Kept separate from the regular phone so the
            // click-to-chat link can target the right number.
            $table->string('whatsapp', 40)->nullable()->after('phone');
            $table->string('referred_by')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn(['whatsapp', 'referred_by']);
        });
    }
};
