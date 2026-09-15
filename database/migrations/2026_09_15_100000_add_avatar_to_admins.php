<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            // Team-chat profile photo. Holds either an uploaded file token
            // ("team-avatars/<id>.jpg"), a bundled filename, or the sentinel "-"
            // meaning the person deliberately removed their photo (show a monogram).
            if (!Schema::hasColumn('admins', 'avatar')) {
                $table->string('avatar')->nullable()->after('full_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            if (Schema::hasColumn('admins', 'avatar')) {
                $table->dropColumn('avatar');
            }
        });
    }
};
