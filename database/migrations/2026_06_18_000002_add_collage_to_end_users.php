<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            // Single combined identity document ("Collage") that replaces the
            // separate Photo ID / Proof of Address / SSN Picture uploads.
            $table->string('collage_path')->nullable()->after('ssn_picture_path');
        });
    }

    public function down(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn('collage_path');
        });
    }
};
