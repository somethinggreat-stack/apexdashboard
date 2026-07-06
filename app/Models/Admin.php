<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable;

    // NOTE: role + parent_admin_id are intentionally NOT mass-assignable — they
    // are set only via explicit assignment (seeder / UserController) so a stray
    // create()/update() can never escalate an account. Security invariant.
    protected $fillable = ['email', 'password', 'full_name'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['password' => 'hashed'];

    /**
     * Roles: 'super' (sees everything), 'va' (business-owner workflow only),
     * 'leads' (sales leads pipeline only). Fail CLOSED — an unknown/missing
     * role is treated as least-privileged, never as super.
     */
    public function isSuper(): bool
    {
        return ($this->role ?? 'va') === 'super';
    }

    public function isVa(): bool
    {
        return ($this->role ?? 'va') === 'va';
    }

    /** Leads agent — only the prospects / prospect-leads pipeline. */
    public function isLeads(): bool
    {
        return ($this->role ?? '') === 'leads';
    }

    /**
     * The admin whose data (business owners, clients) this user operates on.
     * VAs share their parent super admin's data; a super admin owns their own.
     */
    public function dataOwnerId(): int
    {
        return $this->parent_admin_id ?: $this->id;
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function processSteps()
    {
        return $this->hasMany(ProcessStep::class, 'created_by_admin_id');
    }
}
