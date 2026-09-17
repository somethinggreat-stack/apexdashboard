<?php
// Extra seeding for the phase9 suite only: 205 attachments in "Credit Repair CFPB", one more
// than a gallery page, so the Files/Photos tabs have something older to page back to.
// No real files are written — the gallery never touches the disk.

use App\Models\Conversation;
use App\Models\TeamMessage;
use Illuminate\Support\Facades\DB;

$conv = Conversation::where('name', 'Credit Repair CFPB')->firstOrFail();
$sender = DB::table('conversation_participants')->where('conversation_id', $conv->id)->value('admin_id');

$msg = TeamMessage::create([
    'conversation_id' => $conv->id, 'type' => 'text', 'sender_id' => $sender, 'body' => 'a pile of files',
]);

$rows = [];
for ($i = 1; $i <= 205; $i++) {
    $rows[] = ['team_message_id' => $msg->id, 'disk_path' => 'seed/' . $i . '.pdf',
        'original_name' => 'seed-file-' . $i . '.pdf', 'mime' => 'application/pdf', 'size' => 1024,
        'created_at' => now(), 'updated_at' => now()];
}
DB::table('message_attachments')->insert($rows);

echo 'SEEDED FILES conv=' . $conv->id . ' count=' . count($rows) . PHP_EOL;
