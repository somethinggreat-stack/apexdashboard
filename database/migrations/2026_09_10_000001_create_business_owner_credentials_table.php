<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A vault of the CRM / software logins for each business owner (their GoHighLevel,
 * Dispute software, email inbox, etc.). Super admin can always manage these; a VA
 * only when granted (admins.can_manage_credentials). Scoped to the selected owner,
 * shown just above their Tasks View.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_owner_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('software_name');           // "GoHighLevel", "Dispute Panda", "Gmail", …
            $table->string('login_url')->nullable();    // where to sign in
            $table->string('username')->nullable();     // login/username handle
            $table->string('email')->nullable();        // account email
            $table->text('password')->nullable();       // the secret
            $table->text('notes')->nullable();          // 2FA phone, security answers, anything else
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['client_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_owner_credentials');
    }
};
