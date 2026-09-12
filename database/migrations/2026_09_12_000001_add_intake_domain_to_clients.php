<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-business-owner client-facing intake domain. When set, the Secure Intake
 * Link is built on this host (a Cloudflare Worker custom domain that proxies the
 * intake form) so the client sees the owner's own brand instead of the app's
 * real domain. Empty = fall back to the app URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('intake_domain')->nullable()->after('intake_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('intake_domain');
        });
    }
};
