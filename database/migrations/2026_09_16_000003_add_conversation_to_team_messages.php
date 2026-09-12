<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move team messages onto conversations. Adds conversation_id + type (text|system),
 * makes recipient_id nullable (group/system messages have no single recipient),
 * then backfills: one "dm" conversation per existing 1:1 pair, its participants,
 * and each participant's read watermark derived from the old per-message read_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_messages', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->after('id')->constrained('conversations')->cascadeOnDelete();
            $table->string('type', 16)->default('text')->after('conversation_id');   // text | system
        });

        Schema::table('team_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('recipient_id')->nullable()->change();
        });

        $this->backfill();
    }

    private function backfill(): void
    {
        $now = now();

        // One conversation per unordered {sender, recipient} pair.
        $made = [];   // "a-b" (a<b) => conversation id
        $pairs = DB::table('team_messages')->whereNotNull('recipient_id')
            ->select('sender_id', 'recipient_id')->distinct()->get();

        foreach ($pairs as $p) {
            $a = min($p->sender_id, $p->recipient_id);
            $b = max($p->sender_id, $p->recipient_id);
            $key = $a . '-' . $b;
            if (isset($made[$key])) continue;

            $adminA = DB::table('admins')->where('id', $a)->first();
            if (! $adminA) continue;
            $owner = $adminA->parent_admin_id ?: $adminA->id;

            $cid = DB::table('conversations')->insertGetId([
                'type' => 'dm', 'data_owner_id' => $owner, 'created_by' => $a,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('conversation_participants')->insert([
                ['conversation_id' => $cid, 'admin_id' => $a, 'role' => 'member', 'joined_at' => $now, 'created_at' => $now, 'updated_at' => $now],
                ['conversation_id' => $cid, 'admin_id' => $b, 'role' => 'member', 'joined_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ]);
            $made[$key] = $cid;
        }

        // Point every message at its conversation.
        foreach ($made as $key => $cid) {
            [$a, $b] = explode('-', $key);
            DB::table('team_messages')
                ->where(fn ($q) => $q->where('sender_id', $a)->where('recipient_id', $b))
                ->orWhere(fn ($q) => $q->where('sender_id', $b)->where('recipient_id', $a))
                ->update(['conversation_id' => $cid]);
        }

        // Conversation last-message pointers + each participant's read watermark.
        foreach (DB::table('conversations')->pluck('id') as $cid) {
            $last = DB::table('team_messages')->where('conversation_id', $cid)->orderByDesc('id')->first();
            if ($last) {
                DB::table('conversations')->where('id', $cid)->update([
                    'last_message_id' => $last->id, 'last_message_at' => $last->created_at,
                ]);
            }

            foreach (DB::table('conversation_participants')->where('conversation_id', $cid)->get() as $pt) {
                // Read up to: the latest message I sent, or the latest sent to me that was read.
                $watermark = DB::table('team_messages')->where('conversation_id', $cid)
                    ->where(fn ($q) => $q->where('sender_id', $pt->admin_id)
                        ->orWhere(fn ($q2) => $q2->where('recipient_id', $pt->admin_id)->whereNotNull('read_at')))
                    ->max('id');
                DB::table('conversation_participants')->where('id', $pt->id)
                    ->update(['last_read_message_id' => $watermark]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('team_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_id');
            $table->dropColumn('type');
        });
    }
};
