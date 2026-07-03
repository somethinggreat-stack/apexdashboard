<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Enable our hosted secure intake form for Tycon Stan (same as Tishia:
        // hosted form, free-text monitoring provider), and ensure a token.
        DB::table('clients')->where('business_name', 'Tycon Stan')->update(['intake_enabled' => true]);

        foreach (DB::table('clients')->where('business_name', 'Tycon Stan')->whereNull('intake_token')->pluck('id') as $id) {
            DB::table('clients')->where('id', $id)->update(['intake_token' => Str::random(48)]);
        }
    }

    public function down(): void
    {
        DB::table('clients')->where('business_name', 'Tycon Stan')->update(['intake_enabled' => false]);
    }
};
