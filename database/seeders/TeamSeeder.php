<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Guarantee the super admin account exists (from env) so login never breaks.
     * Idempotent — safe to run on every deploy; never deletes anyone.
     *
     * VAs and leads agents are NOT seeded here. They are managed entirely from
     * the Users & Activity screen (add with a role, delete permanently). Seeding
     * them on every deploy used to re-create accounts the super admin had
     * deleted — the database is the single source of truth for them now.
     */
    public function run(): void
    {
        $super = config('team.super');

        if (empty($super['email']) || empty($super['password'])) {
            // Super creds not configured yet — do nothing (don't break login).
            return;
        }

        $this->sync($super['email'], $super['name'] ?: 'Admin', $super['password'], 'super', null);
    }

    /**
     * Create or update one account. role + parent_admin_id are set via explicit
     * assignment (they are NOT mass-assignable on the Admin model), so this is
     * the single trusted place those privileged columns are written.
     */
    private function sync(string $email, string $name, string $password, string $role, ?int $parentId): Admin
    {
        $admin = Admin::firstOrNew(['email' => $email]);
        $admin->full_name       = $name;
        $admin->password        = $password; // 'hashed' cast hashes on save
        $admin->role            = $role;
        $admin->parent_admin_id = $parentId;
        $admin->save();

        return $admin;
    }
}
