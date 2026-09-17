<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\TeamMessage;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Team Chat keeps only a rolling retention window (default 7 days). Everything
 * older — messages, system notices, reactions, mentions, "delete-for-me" rows,
 * attachments AND the attachment files on the private disk — is deleted for good.
 * Nothing past the window is retained anywhere. Conversations and their members
 * are kept (the sidebar contacts/groups stay); only their old content is cleared.
 */
class PurgeOldTeamChat extends Command
{
    protected $signature = 'team-chat:purge
                            {--days=7 : Keep only chat and files newer than this many days}
                            {--all : Wipe ALL chat content for everyone (ignores --days). Contacts/groups stay.}
                            {--dry-run : Report what would be deleted without deleting anything}';

    protected $description = 'Delete Team Chat messages, attachments and their files older than the retention window (default 7 days), or ALL of them with --all. Nothing purged is kept — not in the database, not on the private disk.';

    public function handle(): int
    {
        $all = (bool) $this->option('all');
        $days = (int) $this->option('days');
        if (! $all && $days < 1) {
            $days = 7;   // guard: a stray --days=0 must never silently wipe everything — use --all for that
        }
        $dry    = (bool) $this->option('dry-run');
        // --all: a far-future cutoff so every message (created before "now + 1 year") is purged.
        $cutoff = $all ? now()->addYear() : now()->subDays($days);
        $disk   = Storage::disk('private');

        // Files belonging to messages that are about to be purged.
        $oldPaths = DB::table('message_attachments as a')
            ->join('team_messages as m', 'm.id', '=', 'a.team_message_id')
            ->where('m.created_at', '<', $cutoff)
            ->pluck('a.disk_path')
            ->filter()
            ->values();

        $oldMessages = TeamMessage::where('created_at', '<', $cutoff)->count();

        $scope = $all ? 'ALL' : "older than {$days} day(s)";

        if ($dry) {
            $orphans = $this->orphanFiles($disk, $all);
            $this->info("[dry-run] Would delete {$oldMessages} message(s) and {$oldPaths->count()} attachment file(s) ({$scope}), plus {$orphans->count()} orphaned file(s).");

            return self::SUCCESS;
        }

        // 1. Delete the old attachment files from the private disk.
        foreach ($oldPaths as $path) {
            $disk->delete($path);
        }

        // 2. Delete the old messages. The FK cascade on team_message_id clears the
        //    message_attachments / message_mentions / message_hides rows with them.
        $deleted = TeamMessage::where('created_at', '<', $cutoff)->delete();

        // 3. Re-point each conversation at its newest surviving message (or clear it
        //    so the sidebar preview / unread state don't reference a gone message).
        foreach (Conversation::query()->cursor() as $conv) {
            $last = TeamMessage::where('conversation_id', $conv->id)->orderByDesc('id')->first();
            $conv->update([
                'last_message_id' => $last?->id,
                'last_message_at' => $last?->created_at,
            ]);
        }

        // 4. Sweep any file on the private disk that no message references any more
        //    (leftovers, interrupted uploads, or content removed by an earlier wipe).
        $orphans = $this->orphanFiles($disk, $all);
        foreach ($orphans as $path) {
            $disk->delete($path);
        }

        $remain = $all ? 'No chat content remains.' : "Only the last {$days} day(s) of chat and files remain.";
        $this->info("Team Chat purge ({$scope}): {$deleted} message(s) removed, {$oldPaths->count()} attachment file(s) deleted, {$orphans->count()} orphaned file(s) swept. {$remain}");

        return self::SUCCESS;
    }

    /**
     * Files under team-chat/ that no attachment row points to any more. A send writes its
     * files before saving the message, so files written in the last few minutes may still be
     * about to get their row — they're left alone unless this is a full --all wipe.
     */
    private function orphanFiles(Filesystem $disk, bool $includeFresh = false)
    {
        $onDisk = collect($disk->allFiles('team-chat'));
        if ($onDisk->isEmpty()) {
            return collect();
        }

        $known = DB::table('message_attachments')->pluck('disk_path')->filter()->flip();
        $freshAfter = now()->subMinutes(10)->getTimestamp();

        return $onDisk
            ->reject(fn ($path) => $known->has($path))
            ->reject(function ($path) use ($disk, $includeFresh, $freshAfter) {
                if ($includeFresh) return false;
                try { return $disk->lastModified($path) > $freshAfter; } catch (\Throwable $e) { return false; }
            })
            ->values();
    }
}
