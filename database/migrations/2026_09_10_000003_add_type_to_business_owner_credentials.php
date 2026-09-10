<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Split the vault into two kinds of entry:
 *  - 'credential' — a CRM / software login (name, link, username, email, password, notes)
 *  - 'link'       — a plain resource link (name + link only), e.g. a Google Sheet or Jotform
 * so a link never asks for login details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_owner_credentials', function (Blueprint $table) {
            $table->string('type')->default('credential')->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('business_owner_credentials', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
