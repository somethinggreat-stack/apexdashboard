<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Sync the super admin + VA + leads-agent users from env (config/team.php).
     * Idempotent — safe to run on every deploy. Only touches accounts whose
     * email + password are set in env; never deletes anyone.
     */
    public function run(): void
    {
        $super = config('team.super');

        if (empty($super['email']) || empty($super['password'])) {
            // Super creds not configured yet — do nothing (don't break login).
            return;
        }

        $superAdmin = $this->sync($super['email'], $super['name'] ?: 'Admin', $super['password'], 'super', null);

        foreach (config('team.vas', []) as $va) {
            if (empty($va['email']) || empty($va['password'])) {
                continue;
            }
            $this->sync($va['email'], $va['name'] ?: $va['email'], $va['password'], 'va', $superAdmin->id);
        }

        foreach (config('team.leads', []) as $lead) {
            if (empty($lead['email']) || empty($lead['password'])) {
                continue;
            }
            $this->sync($lead['email'], $lead['name'] ?: $lead['email'], $lead['password'], 'leads', $superAdmin->id);
        }
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
