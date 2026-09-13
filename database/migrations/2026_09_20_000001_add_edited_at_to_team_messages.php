<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 6 — message editing. edited_at stamps when a message was last edited. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_messages', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('team_messages', fn (Blueprint $t) => $t->dropColumn('edited_at'));
    }
};
