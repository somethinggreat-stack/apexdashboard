<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The phone-number pool is withdrawn.
 *
 * It worked, but every provider that could deliver the codes was either
 * unavailable from here or not worth the price, so the feature is being parked
 * rather than left half-connected in the console.
 *
 * Written to be safe in both directions: it drops what a server that already
 * ran the earlier migrations has, and is a no-op on a fresh install where none
 * of it was ever created. Those earlier migration files are gone, so this is
 * the only thing left that knows these tables existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sms_codes');
        Schema::dropIfExists('sms_numbers');

        // The tables kept their old names on any server that never deployed the
        // rename, so clear those too.
        Schema::dropIfExists('ghl_otps');
        Schema::dropIfExists('ghl_numbers');

        if (Schema::hasColumn('admins', 'can_manage_numbers')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('can_manage_numbers');
            });
        }
    }

    public function down(): void
    {
        // Nothing to restore: the feature's own migrations were removed with it.
        // Bringing it back means bringing those back, not reversing this.
    }
};
