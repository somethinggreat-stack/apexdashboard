<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authenticator-app (TOTP) two-factor sign-in for staff.
 *
 * Nothing here switches 2FA on by itself: an account is only challenged once
 * two_factor_confirmed_at is set (the person scanned the QR code and proved it with a
 * code), and only the roles listed in config('team.two_factor.required_roles') are forced
 * to enrol. The secret and the recovery-code hashes are encrypted by the Admin model casts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            // The last 30-second step accepted, so one code can never be used twice.
            $table->unsignedBigInteger('two_factor_last_step')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step']);
        });
    }
};
