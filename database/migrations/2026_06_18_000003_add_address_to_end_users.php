<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->string('current_address')->nullable()->after('proof_of_address_path');
            $table->string('city', 120)->nullable()->after('current_address');
            $table->string('state', 120)->nullable()->after('city');
            $table->string('zipcode', 20)->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn(['current_address', 'city', 'state', 'zipcode']);
        });
    }
};
