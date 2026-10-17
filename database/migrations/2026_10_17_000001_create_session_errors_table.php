<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every "419 Page Expired" (CSRF token mismatch) is recorded here so a super
 * admin can see who hits it, where, and why (Super Admin → Session Errors).
 * Kept 60 days; the user is never shown the raw 419 any more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_errors', function (Blueprint $table) {
            $table->id();
            $table->string('area', 20);              // admin | portal | chat
            $table->string('method', 10);
            $table->string('path', 255);
            $table->string('host', 120);
            $table->boolean('secure');               // https?
            $table->boolean('had_cookie');           // was the session cookie sent at all?
            $table->string('reason', 40);            // session_expired | cookie_missing | not_https
            $table->string('device', 60);            // e.g. "iPhone · Safari"
            $table->text('user_agent')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('referer', 255)->nullable();
            $table->boolean('was_ajax')->default(false);
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_errors');
    }
};
