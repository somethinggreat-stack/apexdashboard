<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Round limit: some clients are sold a fixed number of rounds (the "2-round
 * list"). round_limit caps how far their rounds strip can go — null = no limit.
 * The cap is enforced in EndUser's saving hook, so no editor (list picker,
 * profile picker, Edit Rounds & Dates, Jarvis) can start a round past it.
 *
 * Clients are matched by ID only — names on this list have spelling variants.
 * Julia Bailey (61) is pending confirmation; Yvina Jean Baptiste has no ID yet
 * and is added later through the super-admin override on her profile.
 * Meagan Foster (621) is excluded: she is on Skool with unlimited rounds.
 */
return new class extends Migration
{
    private const TWO_ROUND_IDS = [
        953, 61, 896, 1031, 822, 867, 820, 797, 788, 774, 765, 76, 83, 709, 683,
        85, 643, 641, 715, 642, 624, 620, 1082, 571, 572, 570, 574, 640, 622,
    ];

    private const ROUND_OPTIONS = [
        '1st Round', '2nd Round', '3rd Round', '4th Round', '5th Round', '6th Round', '7th Round', '8th Round',
        '9th Round', '10th Round', '11th Round', '12th Round', '13th Round', '14th Round', '15th Round',
    ];

    public function up(): void
    {
        Schema::table('end_users', function (Blueprint $table) {
            $table->unsignedTinyInteger('round_limit')->nullable()->after('next_round_override');
        });

        DB::table('end_users')->whereIn('id', self::TWO_ROUND_IDS)->update(['round_limit' => 2]);

        // One-time check: anyone on the list already at round 2+ is flagged now,
        // in their Activity log, so the team sees it from day one. (The list
        // badges are live off current round — they need no backfill.)
        $now = now();
        $rows = DB::table('end_users')->whereIn('id', self::TWO_ROUND_IDS)->get(['id', 'rounds']);
        foreach ($rows as $row) {
            $round = 1;
            foreach ((array) json_decode($row->rounds ?? '[]', true) as $label) {
                $idx = array_search($label, self::ROUND_OPTIONS, true);
                if ($idx !== false) {
                    $round = max($round, $idx + 1);
                }
            }
            if ($round < 2) {
                continue;
            }
            DB::table('client_events')->insert([
                'end_user_id' => $row->id,
                'admin_id'    => null,
                'event'       => 'round_limit',
                'description' => $round > 2
                    ? "Flagged for review — 2-round limit, already on Round {$round}"
                    : 'Flagged — 2-round limit reached (Round 2), no more rounds',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('client_events')->where('event', 'round_limit')->whereNull('admin_id')->delete();

        Schema::table('end_users', function (Blueprint $table) {
            $table->dropColumn('round_limit');
        });
    }
};
