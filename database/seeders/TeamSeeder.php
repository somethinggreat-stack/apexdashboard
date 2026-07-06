<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Sync the super admin + VA users from env (config/team.php). Idempotent —
     * safe to run on every deploy. Only touches accounts whose email + password
     * are set in env; never deletes anyone.
     */
    public function run(): void
    {
        $super = config('team.super');

        if (empty($super['email']) || empty($super['password'])) {
            // Super creds not configured yet — do nothing (don't break login).
            return;
        }

        $superAdmin = Admin::updateOrCreate(
            ['email' => $super['email']],
            [
                'full_name'       => $super['name'] ?: 'Admin',
                'password'        => $super['password'],
                'role'            => 'super',
                'parent_admin_id' => null,
            ]
        );

        foreach (config('team.vas') as $va) {
            if (empty($va['email']) || empty($va['password'])) {
                continue;
            }

            Admin::updateOrCreate(
                ['email' => $va['email']],
                [
                    'full_name'       => $va['name'] ?: $va['email'],
                    'password'        => $va['password'],
                    'role'            => 'va',
                    'parent_admin_id' => $superAdmin->id,
                ]
            );
        }
    }
}
