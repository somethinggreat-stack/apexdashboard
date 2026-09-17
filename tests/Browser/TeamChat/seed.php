<?php
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\TeamMessage;

$super = Admin::create(['email'=>'super@apex.test','password'=>bcrypt('password123'),'full_name'=>'Umair Arshad']);
$super->role='super'; $super->save();

$names = ['Abid Hussain','Mujeeb Rahman','Raja Khuram','Ubaid Dogar','Umair Sajid'];
$firstConv = null;
foreach ($names as $i=>$n){
    $va = Admin::create(['email'=>'va'.$i.'@apex.test','password'=>bcrypt('password123'),'full_name'=>$n]);
    $va->role='va'; $va->parent_admin_id=$super->id; $va->save();
    $c = Conversation::create(['type'=>'dm','data_owner_id'=>$super->id,'dm_key'=>'dm:'.min($super->id,$va->id).'-'.max($super->id,$va->id)]);
    $c->participants()->createMany([
        ['admin_id'=>$super->id,'role'=>'member'],
        ['admin_id'=>$va->id,'role'=>'member'],
    ]);
    $last=null;
    for($m=0;$m<12;$m++){
        $last = TeamMessage::create(['conversation_id'=>$c->id,'type'=>'text','sender_id'=>($m%2?$super->id:$va->id),'body'=>"Message {$m} with {$n}"]);
    }
    $c->update(['last_message_id'=>$last->id]);
    if(!$firstConv) $firstConv=$c;
}

// A group too (to test the fallback-to-navigation path).
$g = Conversation::create(['type'=>'group','name'=>'CFPB Screenshots','data_owner_id'=>$super->id,'icon'=>'🔥']);
$g->participants()->createMany([
    ['admin_id'=>$super->id,'role'=>'admin'],
    ['admin_id'=>Admin::where('email','va0@apex.test')->first()->id,'role'=>'member'],
    ['admin_id'=>Admin::where('email','va1@apex.test')->first()->id,'role'=>'member'],
]);
$gm = TeamMessage::create(['conversation_id'=>$g->id,'type'=>'text','sender_id'=>$super->id,'body'=>'Group hello']);
$g->update(['last_message_id'=>$gm->id]);

echo "SEEDED super=super@apex.test pass=password123 firstConv={$firstConv->id} group={$g->id}\n";

// Second group (for group→group swap tests).
$g2 = \App\Models\Conversation::create(['type'=>'group','name'=>'Credit Repair CFPB','data_owner_id'=>$super->id,'icon'=>'💬']);
$g2->participants()->createMany([
    ['admin_id'=>$super->id,'role'=>'admin'],
    ['admin_id'=>\App\Models\Admin::where('email','va2@apex.test')->first()->id,'role'=>'member'],
    ['admin_id'=>\App\Models\Admin::where('email','va3@apex.test')->first()->id,'role'=>'member'],
    ['admin_id'=>\App\Models\Admin::where('email','va4@apex.test')->first()->id,'role'=>'member'],
]);
$g2m = \App\Models\TeamMessage::create(['conversation_id'=>$g2->id,'type'=>'text','sender_id'=>\App\Models\Admin::where('email','va2@apex.test')->first()->id,'body'=>'Second group hello']);
$g2->update(['last_message_id'=>$g2m->id]);
echo "GROUP2 id={$g2->id}\n";

// A VA with no conversations at all (like a newly added teammate).
$san = \App\Models\Admin::create(['email'=>'va5@apex.test','password'=>bcrypt('password123'),'full_name'=>'Sanwal Khan']);
$san->role='va'; $san->parent_admin_id=$super->id; $san->save();
echo "SANWAL id={$san->id}\n";
