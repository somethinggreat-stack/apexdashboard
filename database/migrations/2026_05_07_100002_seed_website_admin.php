<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('admins')->updateOrInsert(
            ['email' => 'admin@umair.com'],
            [
                'full_name'  => 'Admin',
                'password'   => Hash::make('Umair@1534'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('admins')->where('email', 'admin@umair.com')->delete();
    }
};
