<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Canonical "dm:<smaller>-<larger>" pair key for DMs (null for groups). Lets us find
            // an existing DM in one indexed lookup and narrows the duplicate-creation race.
            $table->string('dm_key', 64)->nullable()->after('type');
            $table->index('dm_key');
        });

        // Backfill existing DMs with their pair key.
        $dms = DB::table('conversations')->where('type', 'dm')->pluck('id');
        foreach ($dms as $cid) {
            $ids = DB::table('conversation_participants')->where('conversation_id', $cid)
                ->orderBy('admin_id')->pluck('admin_id')->all();
            if (count($ids) >= 2) {
                DB::table('conversations')->where('id', $cid)
                    ->update(['dm_key' => 'dm:' . $ids[0] . '-' . $ids[1]]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['dm_key']);
            $table->dropColumn('dm_key');
        });
    }
};
