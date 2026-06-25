<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Track two WhatsApp numbers: the client's own, and the number we used
        // to reach out to them. Phone is dropped — contact is WhatsApp-first.
        Schema::table('prospects', function (Blueprint $table) {
            if (!Schema::hasColumn('prospects', 'outreach_whatsapp')) {
                $table->string('outreach_whatsapp', 40)->nullable()->after('whatsapp');
            }
        });

        if (Schema::hasColumn('prospects', 'phone')) {
            Schema::table('prospects', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            if (!Schema::hasColumn('prospects', 'phone')) {
                $table->string('phone', 40)->nullable()->after('name');
            }
            if (Schema::hasColumn('prospects', 'outreach_whatsapp')) {
                $table->dropColumn('outreach_whatsapp');
            }
        });
    }
};
